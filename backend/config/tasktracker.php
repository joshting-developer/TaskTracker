<?php

return [
    'origins' => explode(',', env('ALLOWED_ORIGINS', 'http://127.0.0.1:5174,http://localhost:5174,http://127.0.0.1:5175')),
    // 重播原結果編碼：讀取永遠相容純JSON與gzip-v1；寫入預設json，確認所有worker已支援雙讀後才改gzip-v1（見docs/receipt-codec-2026-11-12.md）。
    // TT-173：增量同步下一輪水位往前退的秒數，涵蓋時間戳已寫入但較晚提交的交易。
    'sync' => ['settle_seconds' => (int) env('TASKTRACKER_SYNC_SETTLE_SECONDS', 10)],
    'receipts' => ['codec' => env('TASKTRACKER_RECEIPT_CODEC', 'json'), 'min_bytes' => (int) env('TASKTRACKER_RECEIPT_CODEC_MIN_BYTES', 4096)],
];
