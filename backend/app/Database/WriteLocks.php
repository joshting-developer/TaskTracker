<?php

namespace App\Database;

use Illuminate\Support\Facades\DB;

/** MySQL寫入先鎖穩定資料列，再重新取得資格／版本；鎖隨外層交易釋放。 */
class WriteLocks
{
    public function account(?string $userId): void
    {
        if (DB::getDriverName() === 'mysql' && $userId) {
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
        }
    }

    public function workspace(?string $workspaceId): void
    {
        if (DB::getDriverName() === 'mysql' && $workspaceId) {
            DB::table('workspaces')->where('public_id', $workspaceId)->lockForUpdate()->first();
        }
    }

    /** 註冊／登入／接受邀請的低頻帳號流程先取得固定guard，防止空集合競爭。 */
    public function authentication(string $operation, array $input, ?string $userId): void
    {
        if (DB::getDriverName() !== 'mysql' || ! in_array($operation, ['register', 'login', 'acceptWorkspaceInvite'], true)) {
            return;
        }
        DB::table('transaction_guards')->where('name', 'accounts')->lockForUpdate()->first();
        if ($operation === 'login' && is_string($input['email'] ?? null)) {
            $userId = DB::table('users')->where('email', strtolower(trim($input['email'])))->lockForUpdate()->value('id');
        }
        $this->account($userId);
        if (is_string($input['inviteToken'] ?? null)) {
            // locking read不建立舊snapshot；workspace鎖在業務／資格查詢之前。
            $workspaceId = DB::table('workspace_invitations')->join('workspaces', 'workspaces.id', '=', 'workspace_invitations.workspace_id')
                ->where('workspace_invitations.public_id', hash('sha256', $input['inviteToken']))
                ->lockForUpdate()->value('workspaces.public_id');
            $this->workspace($workspaceId);
        }
    }
}
