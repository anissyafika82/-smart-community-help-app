<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramLinkCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Lets an authenticated app user generate a one-time code to prove, from
 * the Telegram side, that they own this FindBack account — sent to the
 * bot as "/link CODE" (see TelegramBotService::handleLink).
 */
class TelegramLinkController extends Controller
{
    private const CODE_LIFETIME_MINUTES = 10;

    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only one live code per user at a time — old ones are dead weight.
        TelegramLinkCode::where('user_id', $user->id)->delete();

        $code = TelegramLinkCode::create([
            'code' => strtoupper(Str::random(6)),
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(self::CODE_LIFETIME_MINUTES),
        ]);

        return response()->json([
            'code' => $code->code,
            'expires_at' => $code->expires_at,
            'bot_username' => 'findback_alerts_bot',
        ]);
    }
}
