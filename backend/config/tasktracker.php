<?php

return [
    'registration' => ['public' => filter_var(env('TASKTRACKER_PUBLIC_REGISTRATION', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)), FILTER_VALIDATE_BOOLEAN)],
    // TT-335：帳號相關限流（註冊、登入、驗證碼）的倍數；只有testing生效, 讓隔離測試能連續註冊, 正式與local固定1。
    'auth_throttle_scale' => env('APP_ENV') === 'testing' ? max(1, (int) env('TASKTRACKER_AUTH_THROTTLE_SCALE', 1)) : 1,
    'origins' => explode(',', env('ALLOWED_ORIGINS', 'http://127.0.0.1:5174,http://localhost:5174,http://127.0.0.1:5175')),
    // 重播原結果編碼：讀取永遠相容純JSON與gzip-v1；寫入預設json，確認所有worker已支援雙讀後才改gzip-v1（見docs/receipt-codec-2026-11-12.md）。
    // TT-173：增量同步下一輪水位往前退的秒數，涵蓋時間戳已寫入但較晚提交的交易。
    'sync' => ['settle_seconds' => (int) env('TASKTRACKER_SYNC_SETTLE_SECONDS', 10)],
    'receipts' => ['codec' => env('TASKTRACKER_RECEIPT_CODEC', 'json'), 'min_bytes' => (int) env('TASKTRACKER_RECEIPT_CODEC_MIN_BYTES', 4096)],
];
