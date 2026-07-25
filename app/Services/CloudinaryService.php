<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side Cloudinary upload — used only by the Telegram bot flow
 * (App\Services\TelegramBotService), which receives a photo directly from
 * Telegram rather than through the Flutter app (which uploads to
 * Cloudinary itself and only ever sends this API the resulting URL).
 * Cloudinary can fetch a remote URL as the upload source, so this just
 * hands it the Telegram file URL rather than downloading the bytes here.
 */
class CloudinaryService
{
    public function uploadFromUrl(string $url): ?string
    {
        $cloudName = config('services.cloudinary.cloud_name');
        $preset = config('services.cloudinary.upload_preset');

        if (! $cloudName || ! $preset) {
            Log::info('Cloudinary not configured; skipping bot photo upload.');

            return null;
        }

        try {
            $response = Http::asForm()->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                'file' => $url,
                'upload_preset' => $preset,
            ]);

            if (! $response->successful()) {
                Log::warning('Cloudinary upload failed.', ['response' => $response->body()]);

                return null;
            }

            return $response->json('secure_url');
        } catch (\Throwable $e) {
            Log::warning('Cloudinary upload failed.', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
