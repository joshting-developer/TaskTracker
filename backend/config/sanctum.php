<?php

return [
    // 對外入口僅Bearer，不接受瀏覽器Session作替代認證。
    'guard' => [],
    'routes' => false,
    'expiration' => null,
    'token_prefix' => 'tt_',
    'last_used_at' => false,
];
