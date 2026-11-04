<?php

namespace App\Repositories;

use App\Domain\DomainError;
use Illuminate\Support\Facades\DB;

// 保留原型 payload 契約, 由伺服器限定 Workspace namespace
/**
 * 依伺服器指定Workspace namespace存取原型紀錄，隔離租戶資料。
 */
class RecordStore
{
    /**
     * 設定此服務使用的資料入口與可信任相依。
     *
     * @param  string  $prefix  伺服器限定的Workspace namespace
     * @return void
     */
    public function __construct(private string $prefix = '') {}

    /**
     * 建立由伺服器決定namespace的租戶資料入口。
     *
     * @param  string  $id  紀錄的固定識別碼
     * @return self
     */
    public function forWorkspace(string $id): self
    {
        return new self('w:'.$id.':');
    }

    /**
     * 取得指定種類與識別碼的紀錄。
     *
     * @param  string  $kind  紀錄種類與重試命名空間
     * @param  string  $id  紀錄的固定識別碼
     * @return ?array
     */
    public function get(string $kind, string $id): ?array
    {
        $value = DB::table('records')->where('kind', $this->prefix.$kind)->where('id', $id)->value('payload');

        return $value === null ? null : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * 取得必要紀錄，找不到時回傳穩定業務錯誤。
     *
     * @param  string  $kind  紀錄種類與重試命名空間
     * @param  string  $id  紀錄的固定識別碼
     * @return array
     */
    public function require(string $kind, string $id): array
    {
        return $this->get($kind, $id) ?? DomainError::fail('NOT_FOUND', '找不到這筆資料, 請重新載入');
    }

    /**
     * 按原始順序取得此namespace的同類紀錄。
     *
     * @param  string  $kind  紀錄種類與重試命名空間
     * @return array
     */
    public function all(string $kind): array
    {
        return DB::table('records')->where('kind', $this->prefix.$kind)->orderBy('sequence')->pluck('payload')->map(fn ($v) => json_decode($v, true, flags: JSON_THROW_ON_ERROR))->all();
    }

    /**
     * 以種類及識別碼保存紀錄而不改變既有DTO。
     *
     * @param  string  $kind  紀錄種類與重試命名空間
     * @param  string  $id  紀錄的固定識別碼
     * @param  array  $value  待驗證或正規化的值
     * @return void
     */
    public function put(string $kind, string $id, array $value): void
    {
        DB::table('records')->updateOrInsert([
            'kind' => $this->prefix.$kind,
            'id' => $id,
        ], ['payload' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }
}
