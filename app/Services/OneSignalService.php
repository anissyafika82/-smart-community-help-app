<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications via OneSignal's REST API. Fails silently (just
 * logs) rather than throwing — a notification failure should never break
 * the underlying action (e.g. approving a request) that triggered it.
 */
class OneSignalService
{
    public function notifyUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! $user->onesignal_player_id) {
            return;
        }

        $appId = config('services.onesignal.app_id');
        $apiKey = config('services.onesignal.rest_api_key');

        if (! $appId || ! $apiKey) {
            Log::info('OneSignal not configured; skipping push notification.', ['title' => $title]);

            return;
        }

        try {
            Http::withHeaders([
                'Authorization' => "Basic {$apiKey}",
                'Content-Type' => 'application/json; charset=utf-8',
            ])->post('https://onesignal.com/api/v1/notifications', [
                'app_id' => $appId,
                'include_player_ids' => [$user->onesignal_player_id],
                'headings' => ['en' => $title],
                'contents' => ['en' => $body],
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OneSignal push notification failed.', ['error' => $e->getMessage()]);
        }
    }
}
