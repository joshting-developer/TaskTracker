<?php

namespace App\Services;

use App\Domain\DomainError;
use App\Domain\Fields;
use App\Repositories\RecordStore;
use Closure;

/**
 * 保存已授權操作的防重複提交結果；呼叫前須完成當次授權。
 */
class RequestReplay
{
    /**
     * 依原始輸入簽章重用已授權操作結果，或保存此次寫入結果。
     *
     * @param  RecordStore  $store  限定工作空間的資料存取入口
     * @param  string  $kind  紀錄種類與重試命名空間
     * @param  string  $userId  操作者或目標成員識別碼
     * @param  string  $operation  已允許的操作名稱
     * @param  array  $input  此次操作的原始輸入欄位
     * @param  Closure  $write  通過當次授權後才可執行的寫入
     * @return array
     */
    public function execute(RecordStore $store, string $kind, string $userId, string $operation, array $input, Closure $write): array
    {
        $requestId = Fields::text($input['requestId'] ?? null, '操作識別碼', 100, true);
        $key = $userId.':'.$requestId;
        $signature = hash('sha256', $this->canonical([$operation, $input]));
        $previous = $store->get($kind, $key);
        if ($previous) {
            if ($previous['signature'] !== $signature) {
                DomainError::fail('REQUEST_CONFLICT', '操作識別碼已使用, 請重新送出');
            }

            return $previous['result'];
        }
        $result = $write();
        $store->put($kind, $key, [
            'id' => $key,
            'signature' => $signature,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * 以既有遞迴排序規則產生穩定JSON簽章內容。
     *
     * @param  mixed  $value  待驗證或正規化的值
     * @return string
     */
    private function canonical(mixed $value): string
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$item) {
                $item = json_decode($this->canonical($item), true);
            }
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
