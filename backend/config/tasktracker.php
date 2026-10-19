<?php

return [
    'origins' => explode(',', env('ALLOWED_ORIGINS', 'http://127.0.0.1:5174,http://localhost:5174,http://127.0.0.1:5175')),
    'smtp' => [
        'host' => env('SMTP_HOST'), 'port' => env('SMTP_PORT', 587), 'secure' => env('SMTP_SECURE', false),
        'user' => env('SMTP_USER'), 'password' => env('SMTP_PASSWORD'), 'from' => env('SMTP_FROM'),
    ],
];
