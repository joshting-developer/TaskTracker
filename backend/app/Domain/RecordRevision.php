<?php

namespace App\Domain;

/**
 * 以嚴格版本比對阻止過期寫入，保留既有衝突訊息。
 */
class RecordRevision
{
    /**
     * 嚴格比對預期與目前版本，拒絕過期寫入。
     *
     * @param  array  $record  目前資料庫紀錄
     * @param  array  $input  此次操作的原始輸入欄位
     * @param  string  $label  欄位或資源顯示名稱
     * @return void
     */
    public static function assertCurrent(array $record, array $input, string $label = '任務'): void
    {
        if (($input['expectedRevision'] ?? null) !== $record['revision']) {
            DomainError::fail('REVISION_CONFLICT', $label.'已被更新, 請重新載入後再修改');
        }
    }
}
