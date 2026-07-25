<?php

namespace App\Services;

use App\Models\ItemReport;

/**
 * Handles incoming Telegram webhook updates — replies to the bot commands
 * a user types directly in a DM with it (/start, /founditems, /lostitems)
 * or the inline buttons attached to those replies. Separate from
 * TelegramService (which only sends) so the read/query side of the bot
 * stays easy to test and extend independently.
 */
class TelegramBotService
{
    private const MAX_RESULTS = 8;

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

        // Strip a "@BotUsername" suffix (Telegram appends it in group chats)
        // and any trailing arguments, so "/founditems@findback_alerts_bot"
        // and "/founditems foo" both match "/founditems".
        $command = strtolower(explode('@', explode(' ', trim($text))[0])[0]);

        match ($command) {
            '/start' => $this->sendWelcome($chatId),
            '/founditems' => $this->sendItemList($chatId, 'found'),
            '/lostitems' => $this->sendItemList($chatId, 'lost'),
            default => $this->sendUnknownCommand($chatId),
        };
    }

    /**
     * A tap on one of the Found/Lost Items buttons attached to the welcome
     * message or an item list — same result as typing the command.
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

        match ($data) {
            'founditems' => $this->sendItemList($chatId, 'found'),
            'lostitems' => $this->sendItemList($chatId, 'lost'),
            default => null,
        };
    }

    private function sendWelcome(int|string $chatId): void
    {
        $this->telegram->sendMessage(
            $chatId,
            "👋 <b>Welcome to FindBack!</b>\n\n"
                .'This bot helps you browse lost &amp; found reports from the community.'."\n\n"
                .'Tap a button below, or join the broadcast channel: @findback_founditems',
            self::MENU_KEYBOARD,
        );
    }

    private function sendItemList(int|string $chatId, string $reportType): void
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

    private function sendUnknownCommand(int|string $chatId): void
    {
        $this->telegram->sendMessage($chatId, "Sorry, I didn't understand that. Try one of the buttons below.", self::MENU_KEYBOARD);
    }
}
