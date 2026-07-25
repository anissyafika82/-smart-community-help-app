<?php

namespace App\Services;

use App\Models\ItemReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Broadcasts newly-posted lost/found item reports to a public Telegram
 * channel via the Bot API, widening visibility beyond app users so the
 * original owner (or the finder) has a better chance of connecting. Fails
 * silently (just logs) rather than throwing — a broadcast failure should
 * never break report creation.
 */
class TelegramService
{
    public function broadcastItemReport(ItemReport $itemReport): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (! $botToken || ! $chatId) {
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

        try {
            $endpoint = $itemReport->image_url ? 'sendPhoto' : 'sendMessage';
            $payload = $itemReport->image_url
                ? ['chat_id' => $chatId, 'photo' => $itemReport->image_url, 'caption' => $caption, 'parse_mode' => 'Markdown']
                : ['chat_id' => $chatId, 'text' => $caption, 'parse_mode' => 'Markdown'];

            $response = Http::post("https://api.telegram.org/bot{$botToken}/{$endpoint}", $payload);

            if (! $response->successful()) {
                Log::warning('Telegram broadcast failed.', ['response' => $response->body()]);
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram broadcast failed.', ['error' => $e->getMessage()]);
        }
    }
}
