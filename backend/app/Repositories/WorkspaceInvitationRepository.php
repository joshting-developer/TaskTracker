<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Database\DatabaseTimes;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 單工作區邀請的保存與有界投影, 不決定授權或寄信 */
class WorkspaceInvitationRepository
{
    /**
     * 以可信公開ID取得最小工作區
     *
     * @param  string  $id  已授權工作區ID
     * @return Workspace 工作區內部鍵與名稱
     */
    public function workspace(string $id): Workspace
    {
        return Workspace::where('public_id', $id)->firstOrFail(['id', 'public_id', 'name']);
    }

    /**
     * 檢查受邀地址是否已是該工作區成員, 包含已停用資格
     *
     * @param  Workspace  $workspace  已授權工作區
     * @param  string  $email  正規化地址
     * @return bool 是否已有資格
     */
    public function hasMember(Workspace $workspace, string $email): bool
    {
        return WorkspaceMember::join('users as u', 'u.id', '=', 'workspace_members.user_id')->where('workspace_members.workspace_id', $workspace->id)->where('u.email', $email)->exists();
    }

    /**
     * 新邀請使同地址的舊待接受連結到期
     *
     * @param  Workspace  $workspace  已授權工作區
     * @param  string  $email  正規化地址
     * @return void
     */
    public function expirePrevious(Workspace $workspace, string $email): void
    {
        WorkspaceInvitation::where('workspace_id', $workspace->id)->where('email', $email)->whereNull('consumed_at')->where('expires_at', '>', DatabaseTimes::store(now()->toISOString()))->update(['expires_at' => DatabaseTimes::store(now()->subSecond()->toISOString())]);
    }

    /**
     * 保存服務已驗證的邀請欄位
     *
     * @param  array<string,mixed>  $attributes  已驗證欄位
     * @return WorkspaceInvitation 新邀請
     */
    public function create(array $attributes): WorkspaceInvitation
    {
        foreach (['created_at', 'expires_at'] as $field) {
            $attributes[$field] = DatabaseTimes::store($attributes[$field]->toISOString());
        }

        return WorkspaceInvitation::create($attributes);
    }

    /**
     * 只投影當工作區未接受且未到期的邀請, 不載入秘密或跨工作區帳號
     *
     * @param  Workspace  $workspace  已授權工作區
     * @param  PageQueryData  $filters  有界頁面與地址搜尋
     * @return LengthAwarePaginator<WorkspaceInvitation> 最小邀請與寄送狀態
     */
    public function pending(Workspace $workspace, PageQueryData $filters): LengthAwarePaginator
    {
        $query = WorkspaceInvitation::query()->leftJoin('mail_deliveries as m', 'm.invitation_id', '=', 'workspace_invitations.id')
            ->where('workspace_invitations.workspace_id', $workspace->id)->whereNull('workspace_invitations.consumed_at')->where('workspace_invitations.expires_at', '>', DatabaseTimes::store(now()->toISOString()));
        if ($filters->search !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->search)).'%';
            $query->whereRaw("LOWER(workspace_invitations.email) LIKE ? ESCAPE '!'", [$term]);
        }

        return $query->orderByDesc('workspace_invitations.expires_at')->orderByDesc('workspace_invitations.id')->paginate($filters->perPage,
            ['workspace_invitations.id', 'workspace_invitations.public_id', 'workspace_invitations.email', 'workspace_invitations.created_at', 'workspace_invitations.expires_at', 'm.status as delivery_status'], 'page', $filters->page);
    }
}
