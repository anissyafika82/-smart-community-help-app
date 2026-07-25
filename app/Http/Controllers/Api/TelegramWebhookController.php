<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Telegram's webhook callbacks (one HTTP POST per message sent to
 * the bot) and hands them off to TelegramBotService. Always returns 200 —
 * Telegram retries aggressively on non-2xx, and a malformed/unhandled
 * update isn't worth a retry storm.
 */
class TelegramWebhookController extends Controller
{
    public function __construct(private readonly TelegramBotService $bot)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $expectedSecret = config('services.telegram.webhook_secret');

        if ($expectedSecret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $expectedSecret) {
            abort(403);
        }

        $this->bot->handleUpdate($request->all());

        return response()->json(['ok' => true]);
    }
}
