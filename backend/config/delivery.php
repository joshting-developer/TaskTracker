<?php

return [
    'connection' => env('MAIL_QUEUE_CONNECTION', 'redis'),
    'queue' => env('MAIL_QUEUE', 'mail'),
    'tries' => 3,
    'backoff' => [30, 120],
];
