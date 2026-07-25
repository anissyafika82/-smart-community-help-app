<?php

namespace App\Services;

use App\Models\ItemReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Telegram Bot API: broadcasts newly-posted
 * lost/found item reports to a public channel, and sends replies to
 * users who message the bot directly (see TelegramBotService). Fails
 * silently (just logs) rather than throwing — a Telegram failure should
 * never break report creation or a bot reply flow.
 */
class TelegramService
{
    public function botToken(): ?string
    {
        return config('services.telegram.bot_token');
    }

    public function isConfigured(): bool
    {
        return (bool) $this->botToken();
    }

    public function broadcastItemReport(ItemReport $itemReport): void
    {
        $chatId = config('services.telegram.chat_id');

        if (! $this->isConfigured() || ! $chatId) {
            Log::info('Telegram not configured; skipping item-report broadcast.');

            return;
        }

        $isFound = $itemReport->report_type === 'found';
        $emoji = $isFound ? '🔍' : '🔎';
        $heading = $isFound ? 'Found Item' : 'Lost Item';
        $callToAction = $isFound
            ? 'Open the FindBack app to claim this item.'
            : "Seen it? Open the FindBack app to let {$itemReport->user->name} know.";

        $caption = "{$emoji} *{$heading}*\n\n"
            ."*{$itemReport->item_name}*\n"
            .$itemReport->description
            ."\n\n"
            .($itemReport->location_name ? "📍 {$itemReport->location_name}\n" : '')
            .($itemReport->category ? "🏷 {$itemReport->category->name}\n" : '')
            ."\n{$callToAction}";

        if ($itemReport->image_url) {
            $this->call('sendPhoto', ['chat_id' => $chatId, 'photo' => $itemReport->image_url, 'caption' => $caption, 'parse_mode' => 'Markdown']);
        } else {
            $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $caption, 'parse_mode' => 'Markdown']);
        }
    }

    /**
     * Sends a plain text reply to a specific chat (used by TelegramBotService
     * to answer /start, /founditems, /lostitems, etc.).
     */
    public function sendMessage(int|string $chatId, string $text): void
    {
        if (! $this->isConfigured()) {
            Log::info('Telegram not configured; skipping message send.');

            return;
        }

        $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown']);
    }

    /**
     * Registers the bot's slash-command menu with Telegram (shown when the
     * user taps the "/" button in the chat). Safe to call repeatedly —
     * it just overwrites the previous list.
     */
    public function registerCommands(array $commands): void
    {
        $this->call('setMyCommands', ['commands' => json_encode($commands)]);
    }

    public function setWebhook(string $url, ?string $secretToken = null): void
    {
        $this->call('setWebhook', array_filter([
            'url' => $url,
            'secret_token' => $secretToken,
        ]));
    }

    private function call(string $method, array $payload): void
    {
        $botToken = $this->botToken();

        if (! $botToken) {
            return;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/{$method}", $payload);

            if (! $response->successful()) {
                Log::warning('Telegram API call failed.', ['method' => $method, 'response' => $response->body()]);
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram API call failed.', ['method' => $method, 'error' => $e->getMessage()]);
        }
    }
}
