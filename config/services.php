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

        'fastapi' => [
        'url' => env('FASTAPI_URL', 'http://127.0.0.1:8000'),
        'secret' => env('FASTAPI_SECRET', 'supersecret123'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    // Face recognition tuning
    // threshold: minimum cosine similarity to accept a match (0.65 = balanced, 0.75 = strict)
    // strong_threshold: if score >= this, skip margin check (very confident match)
    // margin: minimum gap between best and second-best match
    'recognition' => [
        'threshold' => env('RECOGNITION_THRESHOLD', 0.65),
        'strong_threshold' => env('RECOGNITION_STRONG_THRESHOLD', 0.80),
        'margin' => env('RECOGNITION_MARGIN', 0.05),
    ],
];
