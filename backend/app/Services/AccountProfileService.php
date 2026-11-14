<?php

namespace App\Services;

use App\Domain\DomainError;
use App\Domain\Fields;
use App\Models\User;
use App\Repositories\AccountProfileRepository;
use App\Repositories\SettingsRepository;

/** 帳號本人設定不依賴工作區, 舊Workspace RPC保留原重播命名空間 */
class AccountProfileService
{
    /**
     * 注入既有條件更新及帳號重試儲存入口
     *
     * @param  SettingsRepository  $settings  既有本人資料條件更新
     * @param  AccountProfileRepository  $requests  帳號重試紀錄
     * @param  RequestReplay  $replay  既有原始欄位簽章規則
     */
    public function __construct(private SettingsRepository $settings, private AccountProfileRepository $requests, private RequestReplay $replay) {}

    /**
     * 只回本人可編輯資料與版本, 不序列化User Model
     *
     * @param  User  $user  已重驗的本人帳號
     * @return array<string,mixed> 最小個人設定
     */
    public function show(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'revision' => (int) $user->profile_revision];
    }

    /**
     * 在HTTP帳號交易及User鎖內更新本人名稱, 每次重播先驗Session
     *
     * @param  User  $user  已鎖定且重驗的本人帳號
     * @param  array<string,mixed>  $input  名稱、版本與操作識別碼
     * @return array{id:string,revision:int} 最小更新結果
     */
    public function update(User $user, array $input): array
    {
        $requestId = Fields::text($input['requestId'] ?? null, '操作識別碼', 100, true);
        $name = Fields::text($input['name'] ?? null, '顯示名稱', 60, true);
        $signature = $this->replay->signature('updateProfile', $input);
        $previous = $this->requests->receipt($user->id, $requestId);
        if ($previous) {
            if (! hash_equals($previous->signature, $signature)) {
                DomainError::fail('REQUEST_CONFLICT', '操作識別碼已使用, 請重新送出');
            }

            return $previous->result;
        }
        if (($input['expectedRevision'] ?? null) !== (int) $user->profile_revision || ! $this->settings->renameUser($user, $name)) {
            DomainError::fail('REVISION_CONFLICT', '設定已被更新, 請重新載入後再修改');
        }
        $result = ['id' => $user->id, 'revision' => (int) $user->profile_revision + 1];
        $this->requests->save(['user_id' => $user->id, 'request_id' => $requestId, 'signature' => $signature, 'result' => $result]);

        return $result;
    }
}
