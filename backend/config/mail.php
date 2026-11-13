<?php

// Compose只接收MAIL設定的私有快照, 避免把backend/.env的APP_KEY或DB設定一起覆蓋。
$mailSnapshot = json_decode(base64_decode((string) env('TASKTRACKER_MAIL_SETTINGS_B64', ''), true) ?: '{}', true);
$mailSnapshot = is_array($mailSnapshot) ? $mailSnapshot : [];
$mailEnv = static fn (string $key, mixed $default = null): mixed => env($key, array_key_exists($key, $mailSnapshot) ? $mailSnapshot[$key] : $default);
$legacy = ! $mailEnv('MAIL_HOST');

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => $mailEnv('MAIL_MAILER', $legacy && env('SMTP_HOST') ? 'smtp' : 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => $mailEnv('MAIL_SCHEME', $legacy && env('SMTP_SECURE', false) ? 'smtps' : null),
            'url' => $mailEnv('MAIL_URL'),
            'host' => $mailEnv('MAIL_HOST', env('SMTP_HOST', '127.0.0.1')),
            'port' => $mailEnv('MAIL_PORT', $legacy ? env('SMTP_PORT', 587) : 587),
            'username' => $mailEnv('MAIL_USERNAME', $legacy ? env('SMTP_USER') : null),
            'password' => $mailEnv('MAIL_PASSWORD', $legacy ? env('SMTP_PASSWORD') : null),
            'timeout' => 10,
            'require_tls' => true,
            'local_domain' => $mailEnv('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => $mailEnv('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => $mailEnv('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => $mailEnv('MAIL_FROM_ADDRESS', $legacy ? env('SMTP_FROM', 'hello@example.com') : 'hello@example.com'),
        'name' => $mailEnv('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

];
