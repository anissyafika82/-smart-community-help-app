<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service password reset that doesn't need a real email service:
 * the code is sent to the user's linked Telegram chat instead (see
 * App\Models\User::telegram_chat_id, set via TelegramBotService::handleLink).
 * A user who never linked Telegram has no self-service path and has to
 * ask an admin (Api\Admin\UserController::resetPassword).
 */
class PasswordResetController extends Controller
{
    private const CODE_LIFETIME_MINUTES = 10;

    public function __construct(private readonly TelegramService $telegram)
    {
    }

    /**
     * POST /api/forgot-password — body: { email }
     */
    public function requestCode(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! $user->telegram_chat_id) {
            return response()->json([
                'sent' => false,
                'message' => "No Telegram-linked account found for that email. Ask an admin to reset your password instead.",
            ]);
        }

        PasswordResetCode::where('user_id', $user->id)->delete();

        $code = (string) random_int(100000, 999999);
        PasswordResetCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(self::CODE_LIFETIME_MINUTES),
        ]);

        $this->telegram->sendMessage(
            $user->telegram_chat_id,
            "🔑 Your FindBack password reset code is <b>{$code}</b>. It expires in ".self::CODE_LIFETIME_MINUTES.' minutes.',
        );

        return response()->json([
            'sent' => true,
            'message' => 'A reset code was sent to your linked Telegram chat.',
        ]);
    }

    /**
     * POST /api/reset-password-with-code — body: { email, code, new_password }
     */
    public function reset(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::where('email', $fields['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Invalid code.'], 422);
        }

        $resetCode = PasswordResetCode::where('user_id', $user->id)
            ->where('code', $fields['code'])
            ->first();

        if (! $resetCode || $resetCode->isExpired()) {
            return response()->json(['message' => 'That code is invalid or expired.'], 422);
        }

        $user->update(['password' => Hash::make($fields['new_password'])]);
        $resetCode->delete();

        return response()->json(['message' => 'Password reset successfully. You can now log in.']);
    }
}
