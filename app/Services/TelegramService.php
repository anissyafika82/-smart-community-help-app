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
            : 'Seen it? Open the FindBack app to let '.e($itemReport->user->name).' know.';

        $caption = "{$emoji} <b>{$heading}</b>\n\n"
            .'<b>'.e($itemReport->item_name).'</b>'."\n"
            .e($itemReport->description)
            ."\n\n"
            .($itemReport->location_name ? '📍 '.e($itemReport->location_name)."\n" : '')
            .($itemReport->category ? '🏷 '.e($itemReport->category->name)."\n" : '')
            ."\n{$callToAction}";

        if ($itemReport->image_url) {
            $this->call('sendPhoto', ['chat_id' => $chatId, 'photo' => $itemReport->image_url, 'caption' => $caption, 'parse_mode' => 'HTML']);
        } else {
            $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $caption, 'parse_mode' => 'HTML']);
        }
    }

    /**
     * Sends a text reply to a specific chat (used by TelegramBotService to
     * answer /start, /founditems, /lostitems, button taps, etc.). Pass
     * $inlineKeyboard as a list of button rows, e.g.
     * [[['text' => 'Found', 'callback_data' => 'founditems']]], to attach
     * tappable buttons instead of requiring the user to type a command.
     */
    public function sendMessage(int|string $chatId, string $text, ?array $inlineKeyboard = null): void
    {
        if (! $this->isConfigured()) {
            Log::info('Telegram not configured; skipping message send.');

            return;
        }

        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];

        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }

        $this->call('sendMessage', $payload);
    }

    /**
     * Dismisses the loading spinner Telegram shows on an inline button
     * until the tap is acknowledged.
     */
    public function answerCallbackQuery(string $callbackQueryId): void
    {
        $this->call('answerCallbackQuery', ['callback_query_id' => $callbackQueryId]);
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

    /**
     * Resolves a photo's file_id (from an incoming message) to a temporary
     * download URL, so it can be handed to Cloudinary as an upload source.
     */
    public function getFileUrl(string $fileId): ?string
    {
        $botToken = $this->botToken();

        if (! $botToken) {
            return null;
        }

        try {
            $response = Http::get("https://api.telegram.org/bot{$botToken}/getFile", ['file_id' => $fileId]);

            if (! $response->successful()) {
                Log::warning('Telegram getFile failed.', ['response' => $response->body()]);

                return null;
            }

            $filePath = $response->json('result.file_path');

            return $filePath ? "https://api.telegram.org/file/bot{$botToken}/{$filePath}" : null;
        } catch (\Throwable $e) {
            Log::warning('Telegram getFile failed.', ['error' => $e->getMessage()]);

            return null;
        }
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
