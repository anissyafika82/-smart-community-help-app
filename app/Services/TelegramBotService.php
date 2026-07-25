<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ItemReport;
use App\Models\TelegramConversationState;
use App\Models\TelegramLinkCode;
use App\Models\User;

/**
 * Handles incoming Telegram webhook updates — replies to the bot commands
 * a user types directly in a DM with it (/start, /founditems, /lostitems,
 * /link, /addfoundreport, /addlostreport, /cancel), the inline buttons
 * attached to those replies, and the free-text answers of an in-progress
 * /addfoundreport or /addlostreport conversation. Separate from
 * TelegramService (which only sends) so the read/query side of the bot
 * stays easy to test and extend independently.
 */
class TelegramBotService
{
    private const MAX_RESULTS = 8;

    private const STEP_ITEM_NAME = 'item_name';
    private const STEP_DESCRIPTION = 'description';
    private const STEP_CATEGORY = 'category';
    private const STEP_LOCATION = 'location';

    private const MENU_KEYBOARD = [
        [
            ['text' => '🔍 Found Items', 'callback_data' => 'founditems'],
            ['text' => '🔎 Lost Items', 'callback_data' => 'lostitems'],
        ],
    ];

    public function __construct(private readonly TelegramService $telegram)
    {
    }

    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;
        $text = $message['text'] ?? null;
        $chatId = $message['chat']['id'] ?? null;

        if (! $chatId || ! $text) {
            return;
        }

        $text = trim($text);

        // Anything that isn't a "/command" is treated as the answer to
        // whatever step an in-progress /addfoundreport or /addlostreport
        // conversation is waiting on (if any).
        if (! str_starts_with($text, '/')) {
            $this->continueConversation((string) $chatId, $text);

            return;
        }

        // Strip a "@BotUsername" suffix (Telegram appends it in group chats)
        // and any trailing arguments, so "/founditems@findback_alerts_bot"
        // and "/founditems foo" both match "/founditems".
        $parts = explode(' ', $text, 2);
        $command = strtolower(explode('@', $parts[0])[0]);
        $argument = trim($parts[1] ?? '');

        match ($command) {
            '/start' => $this->sendWelcome((string) $chatId),
            '/founditems' => $this->sendItemList((string) $chatId, 'found'),
            '/lostitems' => $this->sendItemList((string) $chatId, 'lost'),
            '/link' => $this->handleLink((string) $chatId, $argument),
            '/addfoundreport' => $this->startAddReport((string) $chatId, 'found'),
            '/addlostreport' => $this->startAddReport((string) $chatId, 'lost'),
            '/cancel' => $this->cancelConversation((string) $chatId),
            default => $this->sendUnknownCommand((string) $chatId),
        };
    }

    /**
     * A tap on one of the inline buttons — Found/Lost Items, or a category
     * choice mid-way through /addfoundreport or /addlostreport.
     */
    private function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackId = $callbackQuery['id'] ?? null;
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $data = $callbackQuery['data'] ?? null;

        if ($callbackId) {
            $this->telegram->answerCallbackQuery($callbackId);
        }

        if (! $chatId) {
            return;
        }

        $chatId = (string) $chatId;

        if ($data !== null && str_starts_with($data, 'category:')) {
            $this->selectCategory($chatId, (int) substr($data, strlen('category:')));

            return;
        }

        match ($data) {
            'founditems' => $this->sendItemList($chatId, 'found'),
            'lostitems' => $this->sendItemList($chatId, 'lost'),
            default => null,
        };
    }

    private function sendWelcome(string $chatId): void
    {
        $this->telegram->sendMessage(
            $chatId,
            "👋 <b>Welcome to FindBack!</b>\n\n"
                .'This bot helps you browse lost &amp; found reports from the community, and — once your account is linked — report one yourself.'."\n\n"
                .'<b>Browse:</b> tap a button below, or type /founditems / /lostitems'."\n"
                .'<b>Report an item:</b> /addfoundreport or /addlostreport (link your account first with /link — see Profile in the app)'."\n\n"
                .'Or join the broadcast channel: @findback_founditems',
            self::MENU_KEYBOARD,
        );
    }

    private function sendItemList(string $chatId, string $reportType): void
    {
        $items = ItemReport::query()
            ->where('report_type', $reportType)
            ->open()
            ->with('category')
            ->latest()
            ->limit(self::MAX_RESULTS)
            ->get();

        if ($items->isEmpty()) {
            $label = $reportType === 'found' ? 'found' : 'lost';
            $this->telegram->sendMessage($chatId, "No {$label} items reported right now. Check back later!", self::MENU_KEYBOARD);

            return;
        }

        $emoji = $reportType === 'found' ? '🔍' : '🔎';
        $lines = $items->map(function (ItemReport $item) use ($emoji) {
            $location = $item->location_name ? ' — '.e($item->location_name) : '';
            $category = $item->category ? ' ('.e($item->category->name).')' : '';

            return "{$emoji} <b>".e($item->item_name)."</b>{$location}{$category}\n".e($item->description);
        })->implode("\n\n");

        $this->telegram->sendMessage(
            $chatId,
            "{$lines}\n\nOpen the FindBack app for full details and to claim an item.",
            self::MENU_KEYBOARD,
        );
    }

    /**
     * "/link CODE" — proves this Telegram chat belongs to whoever generated
     * CODE in the app (see TelegramLinkController::generate), so the bot
     * knows which FindBack account to post future reports under.
     */
    private function handleLink(string $chatId, string $code): void
    {
        if ($code === '') {
            $this->telegram->sendMessage($chatId, 'Usage: /link CODE — get a code from the app under Profile > Link Telegram Bot.');

            return;
        }

        $linkCode = TelegramLinkCode::where('code', strtoupper($code))->first();

        if (! $linkCode || $linkCode->isExpired()) {
            $this->telegram->sendMessage($chatId, "That code is invalid or expired. Generate a new one in the app and try again.");

            return;
        }

        // A chat can only ever be linked to one account — drop any previous
        // link so re-linking (e.g. a different user's phone) is clean.
        User::where('telegram_chat_id', $chatId)->update(['telegram_chat_id' => null]);

        $linkCode->user->update(['telegram_chat_id' => $chatId]);
        $linkCode->delete();

        $this->telegram->sendMessage(
            $chatId,
            "✅ Linked as <b>".e($linkCode->user->name)."</b>. You can now use /addfoundreport and /addlostreport.",
        );
    }

    private function startAddReport(string $chatId, string $reportType): void
    {
        $user = User::where('telegram_chat_id', $chatId)->first();

        if (! $user) {
            $this->telegram->sendMessage(
                $chatId,
                "You need to link your FindBack account first. Open the app > Profile > Link Telegram Bot, then send /link CODE here.",
            );

            return;
        }

        TelegramConversationState::updateOrCreate(
            ['chat_id' => $chatId],
            ['user_id' => $user->id, 'step' => self::STEP_ITEM_NAME, 'payload' => ['report_type' => $reportType]],
        );

        $label = $reportType === 'found' ? 'found' : 'lost';
        $this->telegram->sendMessage($chatId, "Reporting a {$label} item. What's the item's name? (/cancel to stop)");
    }

    private function cancelConversation(string $chatId): void
    {
        $deleted = TelegramConversationState::where('chat_id', $chatId)->delete();

        $this->telegram->sendMessage($chatId, $deleted ? 'Cancelled.' : 'Nothing to cancel.', self::MENU_KEYBOARD);
    }

    /**
     * Routes a plain-text message to whichever step of /addfoundreport or
     * /addlostreport is currently in progress for this chat, if any.
     */
    private function continueConversation(string $chatId, string $text): void
    {
        $state = TelegramConversationState::where('chat_id', $chatId)->first();

        if (! $state) {
            $this->sendUnknownCommand($chatId);

            return;
        }

        match ($state->step) {
            self::STEP_ITEM_NAME => $this->collectItemName($state, $text),
            self::STEP_DESCRIPTION => $this->collectDescription($state, $text),
            self::STEP_LOCATION => $this->collectLocationAndCreate($state, $text),
            default => null,
        };
    }

    private function collectItemName(TelegramConversationState $state, string $text): void
    {
        $state->update(['payload' => [...$state->payload, 'item_name' => $text], 'step' => self::STEP_DESCRIPTION]);
        $this->telegram->sendMessage($state->chat_id, 'Got it. Now describe the item (colour, brand, condition, etc.):');
    }

    private function collectDescription(TelegramConversationState $state, string $text): void
    {
        $state->update(['payload' => [...$state->payload, 'description' => $text], 'step' => self::STEP_CATEGORY]);

        $categories = Category::orderBy('name')->get();
        $buttons = $categories->chunk(2)->map(
            fn ($chunk) => $chunk->map(fn (Category $c) => ['text' => $c->name, 'callback_data' => "category:{$c->id}"])->values()->all(),
        )->values()->all();

        $this->telegram->sendMessage($state->chat_id, 'Which category?', $buttons);
    }

    private function selectCategory(string $chatId, int $categoryId): void
    {
        $state = TelegramConversationState::where('chat_id', $chatId)->where('step', self::STEP_CATEGORY)->first();

        if (! $state || ! Category::whereKey($categoryId)->exists()) {
            return;
        }

        $state->update(['payload' => [...$state->payload, 'category_id' => $categoryId], 'step' => self::STEP_LOCATION]);
        $this->telegram->sendMessage($chatId, "Where was it lost/found? (or send 'skip')");
    }

    private function collectLocationAndCreate(TelegramConversationState $state, string $text): void
    {
        $locationName = strtolower($text) === 'skip' ? null : $text;
        $payload = [...$state->payload, 'location_name' => $locationName];
        $user = $state->user;

        $itemReport = $user->itemReports()->create([
            'category_id' => $payload['category_id'],
            'report_type' => $payload['report_type'],
            'item_name' => $payload['item_name'],
            'description' => $payload['description'],
            'date_lost_or_found' => now()->toDateString(),
            'location_name' => $locationName,
            'status' => $payload['report_type'],
        ]);

        $state->delete();

        $itemReport->refresh()->load(['user', 'category']);
        $this->telegram->broadcastItemReport($itemReport);

        $this->telegram->sendMessage(
            $state->chat_id,
            "✅ Report posted! Other users can now see <b>".e($itemReport->item_name)."</b> in the FindBack app.",
            self::MENU_KEYBOARD,
        );
    }

    private function sendUnknownCommand(string $chatId): void
    {
        $this->telegram->sendMessage($chatId, "Sorry, I didn't understand that. Try one of the buttons below, or /addfoundreport / /addlostreport.", self::MENU_KEYBOARD);
    }
}
