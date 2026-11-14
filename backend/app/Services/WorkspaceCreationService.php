<?php

namespace App\Services;

use App\Domain\DomainError;
use App\Domain\Fields;
use App\Models\User;
use App\Repositories\WorkspaceCreationRepository;
use Illuminate\Support\Str;

/** 既有帳號建立自己的空間, 沿用HTTP帳號交易與User鎖 */
class WorkspaceCreationService
{
    /**
     * 注入建立結果與新空間資料入口
     *
     * @param  WorkspaceCreationRepository  $creations  帳號限定的資料存取
     */
    public function __construct(private WorkspaceCreationRepository $creations) {}

    /**
     * 建立一次或重播本人仍可存取的結果, 不改既有空間資格
     *
     * @param  User  $user  在User鎖內重驗的帳號
     * @param  array<string,mixed>  $input  名稱與請求識別碼
     * @return string 新空間公開ID, 由Session邊界切換
     */
    public function create(User $user, array $input): string
    {
        if ($user->email_verification_required && ! $user->email_verified_at) {
            DomainError::fail('FORBIDDEN', '請先完成信箱驗證');
        }
        $name = Fields::text($input['workspaceName'] ?? null, '工作空間名稱', 60, true);
        $requestId = Fields::text($input['requestId'] ?? null, '操作識別碼', 36, true);
        if (! Str::isUuid($requestId)) {
            DomainError::fail('INVALID_INPUT', '操作識別碼格式不正確');
        }
        $signature = hash('sha256', $name);
        $previous = $this->creations->receipt($user->id, $requestId);
        if ($previous) {
            if (! hash_equals($previous->signature, $signature)) {
                DomainError::fail('REQUEST_CONFLICT', '操作識別碼已使用, 請重新送出');
            }
            $workspace = $this->creations->workspace($previous->workspace_id);
            if (! $workspace) {
                DomainError::fail('NOT_FOUND', '原工作空間已不存在');
            }
            if (! $this->creations->active($workspace->id, $user->id)) {
                DomainError::fail('FORBIDDEN', '工作空間資格已失效');
            }

            return $workspace->public_id;
        }
        $workspace = $this->creations->create(['public_id' => (string) Str::uuid(), 'name' => $name, 'created_at' => now()]);
        $this->creations->saveCreation(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner', 'joined_at' => now()], ['user_id' => $user->id, 'request_id' => $requestId, 'signature' => $signature, 'workspace_id' => $workspace->id]);

        return $workspace->public_id;
    }
}
