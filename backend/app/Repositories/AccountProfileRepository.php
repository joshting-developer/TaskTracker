<?php

namespace App\Repositories;

use App\Models\AccountProfileRequest;

/** 本人設定重試資料入口, 以User及requestId限制資料 */
class AccountProfileRepository
{
    /**
     * 只讀本人指定的單筆重試結果
     *
     * @param  string  $userId  已驗證的本人帳號
     * @param  string  $requestId  此次操作識別碼
     * @return ?AccountProfileRequest 已儲存的結果或null
     */
    public function receipt(string $userId, string $requestId): ?AccountProfileRequest
    {
        return AccountProfileRequest::where('user_id', $userId)->where('request_id', $requestId)->first();
    }

    /**
     * 與外層帳號交易一併保存最小結果
     *
     * @param  array<string,mixed>  $attributes  本人操作、簽章及最小結果
     * @return void
     */
    public function save(array $attributes): void
    {
        AccountProfileRequest::create($attributes);
    }
}
