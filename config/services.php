<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'cloudinary' => [
        // Used only for reference/documentation; the Flutter app uploads
        // directly to Cloudinary using an unsigned upload preset and
        // sends the resulting secure_url to this API.
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
    ],

    'onesignal' => [
        // From OneSignal dashboard > Settings > Keys & IDs.
        'app_id' => env('ONESIGNAL_APP_ID'),
        'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
    ],

    'telegram' => [
        // Bot token from @BotFather. Chat ID can be a numeric group/channel
        // ID or, for a public channel, "@channelusername".
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        // Verifies incoming /telegram/webhook calls really come from
        // Telegram (sent back as X-Telegram-Bot-Api-Secret-Token). Set via
        // setWebhook — see routes/api.php and the TELEGRAM setup notes.
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

];
