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
 * attached to those replies, and the free-text/photo/location answers of
 * an in-progress /addfoundreport or /addlostreport conversation. Walks
 * through the same fields as the app's "Report an Item" screen: item
 * name, description, category, location name, date, private identifying
 * details, verification questions (found items only), a photo, and an
 * exact GPS location. Separate from TelegramService (which only sends) so
 * the read/query side of the bot stays easy to test and extend
 * independently.
 */
class TelegramBotService
{
    private const MAX_RESULTS = 8;

    private const STEP_ITEM_NAME = 'item_name';
    private const STEP_DESCRIPTION = 'description';
    private const STEP_CATEGORY = 'category';
    private const STEP_LOCATION_NAME = 'location_name';
    private const STEP_DATE = 'date';
    private const STEP_IDENTIFYING_DETAILS = 'identifying_details';
    private const STEP_VERIFICATION = 'verification';
    private const STEP_PHOTO = 'photo';
    private const STEP_GPS = 'gps';

    private const MENU_KEYBOARD = [
        [
            ['text' => '🔍 Found Items', 'callback_data' => 'founditems'],
            ['text' => '🔎 Lost Items', 'callback_data' => 'lostitems'],
        ],
    ];

    public function __construct(
        private readonly TelegramService $telegram,
        private readonly CloudinaryService $cloudinary,
    ) {
    }

    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;
        $chatId = $message['chat']['id'] ?? null;

        if (! $chatId) {
            return;
        }

        $chatId = (string) $chatId;

        if (isset($message['photo'])) {
            $this->continueConversationWithPhoto($chatId, $message['photo']);

            return;
        }

        if (isset($message['location'])) {
            $this->continueConversationWithLocation($chatId, $message['location']);

            return;
        }

        $text = $message['text'] ?? null;

        if (! $text) {
            return;
        }

        $text = trim($text);

        // Anything that isn't a "/command" is treated as the answer to
        // whatever step an in-progress /addfoundreport or /addlostreport
        // conversation is waiting on (if any).
        if (! str_starts_with($text, '/')) {
            $this->continueConversation($chatId, $text);

            return;
        }

        // Strip a "@BotUsername" suffix (Telegram appends it in group chats)
        // and any trailing arguments, so "/founditems@findback_alerts_bot"
        // and "/founditems foo" both match "/founditems".
        $parts = explode(' ', $text, 2);
        $command = strtolower(explode('@', $parts[0])[0]);
        $argument = trim($parts[1] ?? '');

        match ($command) {
            '/start' => $this->sendWelcome($chatId),
            '/link' => $this->handleLink($chatId, $argument),
            '/cancel' => $this->cancelConversation($chatId),
            '/founditems' => $this->withLinkedUser($chatId, fn () => $this->sendItemList($chatId, 'found')),
            '/lostitems' => $this->withLinkedUser($chatId, fn () => $this->sendItemList($chatId, 'lost')),
            '/addfoundreport' => $this->withLinkedUser($chatId, fn ($user) => $this->startAddReport($chatId, $user, 'found')),
            '/addlostreport' => $this->withLinkedUser($chatId, fn ($user) => $this->startAddReport($chatId, $user, 'lost')),
            default => $this->sendUnknownCommand($chatId),
        };
    }

    /**
     * Every command except /start, /link, and /cancel requires a linked
     * account — runs $action(User) if this chat is linked, otherwise tells
     * the user how to link first.
     */
    private function withLinkedUser(string $chatId, callable $action): void
    {
        $user = User::where('telegram_chat_id', $chatId)->first();

        if (! $user) {
            $this->telegram->sendMessage(
                $chatId,
                "🔒 Please link your FindBack account first: open the app > Profile > Link Telegram Bot, then send /link CODE here.",
            );

            return;
        }

        $action($user);
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
            'founditems' => $this->withLinkedUser($chatId, fn () => $this->sendItemList($chatId, 'found')),
            'lostitems' => $this->withLinkedUser($chatId, fn () => $this->sendItemList($chatId, 'lost')),
            default => null,
        };
    }

    private function sendWelcome(string $chatId): void
    {
        $isLinked = User::where('telegram_chat_id', $chatId)->exists();

        if (! $isLinked) {
            $this->telegram->sendMessage(
                $chatId,
                "👋 <b>Welcome to FindBack!</b>\n\n"
                    .'This bot lets you browse and report lost &amp; found items from the community — but first, link it to your FindBack account:'."\n\n"
                    .'1. Open the app > Profile > Link Telegram Bot'."\n"
                    .'2. Send the code here as: /link CODE'."\n\n"
                    .'Or just follow the broadcast channel: @findback_founditems',
            );

            return;
        }

        $this->telegram->sendMessage(
            $chatId,
            "👋 <b>Welcome back!</b>\n\n"
                .'<b>Browse:</b> tap a button below, or type /founditems / /lostitems'."\n"
                .'<b>Report an item:</b> /addfoundreport or /addlostreport'."\n\n"
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

    private function startAddReport(string $chatId, User $user, string $reportType): void
    {
        TelegramConversationState::updateOrCreate(
            ['chat_id' => $chatId],
            ['user_id' => $user->id, 'step' => self::STEP_ITEM_NAME, 'payload' => ['report_type' => $reportType]],
        );

        $label = $reportType === 'found' ? 'found' : 'lost';
        $this->telegram->sendMessage($chatId, "Reporting a {$label} item. What's the item's name? (/cancel any time to stop)");
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
            self::STEP_LOCATION_NAME => $this->collectLocationName($state, $text),
            self::STEP_DATE => $this->collectDate($state, $text),
            self::STEP_IDENTIFYING_DETAILS => $this->collectIdentifyingDetails($state, $text),
            self::STEP_VERIFICATION => $this->collectVerification($state, $text),
            self::STEP_PHOTO => $this->skipPhoto($state, $text),
            self::STEP_GPS => $this->skipGps($state, $text),
            default => null,
        };
    }

    /**
     * A photo sent while STEP_PHOTO is active — Telegram delivers it as
     * several resolutions of the same image; the last is the largest.
     */
    private function continueConversationWithPhoto(string $chatId, array $photos): void
    {
        $state = TelegramConversationState::where('chat_id', $chatId)->where('step', self::STEP_PHOTO)->first();

        if (! $state) {
            return;
        }

        $fileId = end($photos)['file_id'] ?? null;

        if (! $fileId) {
            $this->telegram->sendMessage($chatId, "Couldn't read that photo — please try again, or send 'skip'.");

            return;
        }

        $fileUrl = $this->telegram->getFileUrl($fileId);
        $imageUrl = $fileUrl ? $this->cloudinary->uploadFromUrl($fileUrl) : null;

        if (! $imageUrl) {
            $this->telegram->sendMessage($chatId, "Couldn't upload that photo — continuing without it.");
        }

        $this->advanceToGps($state, $imageUrl);
    }

    /**
     * A shared Telegram location while STEP_GPS is active (📎 > Location).
     */
    private function continueConversationWithLocation(string $chatId, array $location): void
    {
        $state = TelegramConversationState::where('chat_id', $chatId)->where('step', self::STEP_GPS)->first();

        if (! $state) {
            return;
        }

        $this->finishAddReport($state, $location['latitude'] ?? null, $location['longitude'] ?? null);
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

        $state->update(['payload' => [...$state->payload, 'category_id' => $categoryId], 'step' => self::STEP_LOCATION_NAME]);
        $this->telegram->sendMessage($chatId, "Where was it lost/found? (or send 'skip')");
    }

    private function collectLocationName(TelegramConversationState $state, string $text): void
    {
        $locationName = strtolower($text) === 'skip' ? null : $text;
        $state->update(['payload' => [...$state->payload, 'location_name' => $locationName], 'step' => self::STEP_DATE]);
        $this->telegram->sendMessage($state->chat_id, "When did you lose/find it? Send a date like 2026-07-20, or type 'today'.");
    }

    private function collectDate(TelegramConversationState $state, string $text): void
    {
        $date = $this->parseDate($text);

        if ($date === null) {
            $this->telegram->sendMessage(
                $state->chat_id,
                "Sorry, I couldn't understand that date. Try a format like 2026-07-20, or type 'today'.",
            );

            return;
        }

        $state->update(['payload' => [...$state->payload, 'date_lost_or_found' => $date], 'step' => self::STEP_IDENTIFYING_DETAILS]);
        $this->telegram->sendMessage(
            $state->chat_id,
            "Any private identifying details to help verify ownership later (e.g. a scratch, engraving, serial number)? Only you and the other party will see this. Or send 'skip'.",
        );
    }

    private function parseDate(string $text): ?string
    {
        $text = trim($text);

        if (strtolower($text) === 'today') {
            return now()->toDateString();
        }

        $timestamp = strtotime($text);

        if ($timestamp === false) {
            return null;
        }

        $date = date('Y-m-d', $timestamp);

        return $date > now()->toDateString() ? null : $date;
    }

    private function collectIdentifyingDetails(TelegramConversationState $state, string $text): void
    {
        $details = strtolower($text) === 'skip' ? null : $text;
        $payload = [...$state->payload, 'identifying_details' => $details];

        if ($payload['report_type'] === 'found') {
            $state->update(['payload' => $payload, 'step' => self::STEP_VERIFICATION]);
            $this->telegram->sendMessage(
                $state->chat_id,
                "Optional: add 2-4 verification questions only the true owner would know — claimants must answer all of them. "
                    ."Send one at a time as 'Question | Answer' (e.g. 'What color is the case? | Blue'). "
                    ."Type 'done' when finished (need at least 2), or 'skip' to skip this step.",
            );

            return;
        }

        $state->update(['payload' => $payload, 'step' => self::STEP_PHOTO]);
        $this->telegram->sendMessage($state->chat_id, "Send a photo of the item, or send 'skip' to post without one.");
    }

    private function collectVerification(TelegramConversationState $state, string $text): void
    {
        $lower = strtolower(trim($text));
        $questions = $state->payload['verification_questions'] ?? [];

        if ($lower === 'skip') {
            $this->advanceToPhoto($state, []);

            return;
        }

        if ($lower === 'done') {
            if (count($questions) === 1) {
                $this->telegram->sendMessage($state->chat_id, "Add at least one more (need 2 minimum), or type 'skip' to drop them all.");

                return;
            }

            $this->advanceToPhoto($state, $questions);

            return;
        }

        if (count($questions) >= 4) {
            $this->telegram->sendMessage($state->chat_id, "Maximum of 4 reached — type 'done' to continue.");

            return;
        }

        $parts = array_map('trim', explode('|', $text, 2));

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            $this->telegram->sendMessage($state->chat_id, "Please send it as 'Question | Answer', or type 'done' / 'skip'.");

            return;
        }

        $questions[] = ['question' => $parts[0], 'expected_answer' => $parts[1]];
        $state->update(['payload' => [...$state->payload, 'verification_questions' => $questions]]);
        $this->telegram->sendMessage(
            $state->chat_id,
            'Added ('.count($questions)."/4). Send another, or type 'done' (min 2) or 'skip'.",
        );
    }

    private function advanceToPhoto(TelegramConversationState $state, array $verificationQuestions): void
    {
        $state->update(['payload' => [...$state->payload, 'verification_questions' => $verificationQuestions], 'step' => self::STEP_PHOTO]);
        $this->telegram->sendMessage($state->chat_id, "Send a photo of the item, or send 'skip' to post without one.");
    }

    private function skipPhoto(TelegramConversationState $state, string $text): void
    {
        if (strtolower($text) !== 'skip') {
            $this->telegram->sendMessage($state->chat_id, "Send a photo, or type 'skip' to post without one.");

            return;
        }

        $this->advanceToGps($state, null);
    }

    private function advanceToGps(TelegramConversationState $state, ?string $imageUrl): void
    {
        $state->update(['payload' => [...$state->payload, 'image_url' => $imageUrl], 'step' => self::STEP_GPS]);
        $this->telegram->sendMessage(
            $state->chat_id,
            "Last step: share the exact location for the map (📎 > Location), or send 'skip' to finish without it.",
        );
    }

    private function skipGps(TelegramConversationState $state, string $text): void
    {
        if (strtolower($text) !== 'skip') {
            $this->telegram->sendMessage($state->chat_id, "Share your location, or type 'skip' to finish.");

            return;
        }

        $this->finishAddReport($state, null, null);
    }

    private function finishAddReport(TelegramConversationState $state, ?float $latitude, ?float $longitude): void
    {
        $payload = $state->payload;
        $chatId = $state->chat_id;
        $user = $state->user;

        $itemReport = $user->itemReports()->create([
            'category_id' => $payload['category_id'],
            'report_type' => $payload['report_type'],
            'item_name' => $payload['item_name'],
            'description' => $payload['description'],
            'image_url' => $payload['image_url'] ?? null,
            'date_lost_or_found' => $payload['date_lost_or_found'],
            'location_name' => $payload['location_name'] ?? null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'identifying_details' => $payload['identifying_details'] ?? null,
            'status' => $payload['report_type'],
        ]);

        foreach ($payload['verification_questions'] ?? [] as $question) {
            $itemReport->verificationQuestions()->create($question);
        }

        $state->delete();

        $itemReport->refresh()->load(['user', 'category']);
        $this->telegram->broadcastItemReport($itemReport);

        $this->telegram->sendMessage(
            $chatId,
            "✅ Report posted! Other users can now see <b>".e($itemReport->item_name)."</b> in the FindBack app.",
            self::MENU_KEYBOARD,
        );
    }

    private function sendUnknownCommand(string $chatId): void
    {
        $this->telegram->sendMessage($chatId, "Sorry, I didn't understand that. Try one of the buttons below, or /addfoundreport / /addlostreport.", self::MENU_KEYBOARD);
    }
}
