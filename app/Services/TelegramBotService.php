<?php

namespace App\Services;

use App\Models\ItemReport;

/**
 * Handles incoming Telegram webhook updates — replies to the bot commands
 * a user types directly in a DM with it (/start, /founditems, /lostitems).
 * Separate from TelegramService (which only sends) so the read/query side
 * of the bot stays easy to test and extend independently.
 */
class TelegramBotService
{
    private const MAX_RESULTS = 8;

    public function __construct(private readonly TelegramService $telegram)
    {
    }

    public function handleUpdate(array $update): void
    {
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

    private function sendWelcome(int|string $chatId): void
    {
        $this->telegram->sendMessage($chatId, <<<'MSG'
            👋 *Welcome to FindBack!*

            This bot helps you browse lost & found reports from the community.

            Commands:
            /founditems — recently found items
            /lostitems — recently reported lost items

            Or join the broadcast channel: @findback_founditems
            MSG);
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
            $this->telegram->sendMessage($chatId, "No {$label} items reported right now. Check back later!");

            return;
        }

        $emoji = $reportType === 'found' ? '🔍' : '🔎';
        $lines = $items->map(function (ItemReport $item) use ($emoji) {
            $location = $item->location_name ? " — {$item->location_name}" : '';
            $category = $item->category ? " ({$item->category->name})" : '';

            return "{$emoji} *{$item->item_name}*{$location}{$category}\n{$item->description}";
        })->implode("\n\n");

        $this->telegram->sendMessage(
            $chatId,
            "{$lines}\n\nOpen the FindBack app for full details and to claim an item.",
        );
    }

    private function sendUnknownCommand(int|string $chatId): void
    {
        $this->telegram->sendMessage($chatId, "Sorry, I didn't understand that. Try /founditems or /lostitems.");
    }
}
