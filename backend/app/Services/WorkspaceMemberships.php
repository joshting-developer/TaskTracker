<?php

namespace App\Services;

use App\Domain\DomainError;
use App\Domain\Fields;
use App\Models\User;
use App\Repositories\MembershipRepository;
use App\Factories\RecordStoreFactory;

/** 管理本人跨工作空間資格與單次邀請接受，不改動既有身份或專案ACL。 */
class WorkspaceMemberships
{
    /**
     * 注入工作區資格查詢與資料入口工廠。
     *
     * @param  MembershipRepository  $memberships  有效工作區資格查詢
     * @param  RecordStoreFactory  $stores  依範圍建立資料入口
     */
    public function __construct(private MembershipRepository $memberships, private RecordStoreFactory $stores) {}

    /**
     * 回傳本人的有效工作空間，不包含未加入或停用空間。
     *
     * @param  User  $user  已驗證的登入帳號
     * @return array 公開工作空間ID與名稱
     */
    public function listing(User $user): array
    {
        return $this->memberships->activeForUser($user->id)->map(fn ($member) => [
            'id' => $member->workspace->public_id,
            'name' => $member->workspace->name,
        ])->all();
    }

    /**
     * 以不可猜測的邀請token取得仍有效的邀請。
     *
     * @param  array  $input  邀請token
     * @return array 只供服務內部使用的邀請紀錄
     */
    public function invitation(array $input): array
    {
        $token = Fields::text($input['inviteToken'] ?? null, '邀請碼', 100, true);
        $invite = $this->stores->global()->get('invites', hash('sha256', $token));
        if (! $invite || $invite['expiresAt'] <= now()->utc()->toISOString()) {
            DomainError::fail('INVALID_INPUT', '邀請已失效，請向邀請者索取新連結');
        }

        return $invite;
    }

    /**
     * 讓持有連結的人確認目標空間與受邀Email，不消耗邀請。
     *
     * @param  array  $input  邀請token
     * @return array 可呈現的邀請資料
     */
    public function preview(array $input): array
    {
        $invite = $this->invitation($input);
        $workspace = $this->stores->global()->require('workspaces', $invite['workspaceId']);

        return [
            'workspaceName' => $workspace['name'],
            'email' => $invite['email'],
            'expiresAt' => $invite['expiresAt'],
            'consumed' => ! empty($invite['consumedAt']),
        ];
    }

    /**
     * 驗證本人與Email後接受邀請；重試不重複建立或降級成員。
     *
     * @param  User  $user  已驗證的登入帳號
     * @param  array  $input  邀請token
     * @return string 已加入的工作空間公開ID
     */
    public function accept(User $user, array $input): string
    {
        $invite = $this->invitation($input);
        if (strtolower($user->email) !== $invite['email']) {
            DomainError::fail('FORBIDDEN', '請使用受邀的Email登入後再接受邀請');
        }
        $store = $this->stores->global();
        $store->require('workspaces', $invite['workspaceId']);
        $tenant = $store->forWorkspace($invite['workspaceId']);
        $member = $tenant->get('members', $user->id);
        if ($member && ! empty($member['disabledAt'])) {
            DomainError::fail('FORBIDDEN', '此工作空間資格已停用，請聯絡擁有者');
        }
        if (! empty($invite['consumedAt']) && ! $member) {
            DomainError::fail('INVALID_INPUT', '邀請已使用');
        }
        if (! $member) {
            $tenant->put('members', $user->id, [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'user',
                'joinedAt' => now()->utc()->toISOString(),
            ]);
        }
        if (empty($invite['consumedAt'])) {
            $store->put('invites', $invite['id'], [
                ...$invite,
                'consumedAt' => now()->utc()->toISOString(),
            ]);
        }

        return $invite['workspaceId'];
    }

    /**
     * 只允許選擇本人仍有效的工作空間。
     *
     * @param  User  $user  已驗證的登入帳號
     * @param  array  $input  欲切換的工作空間公開ID
     * @return string 已驗證的工作空間公開ID
     */
    public function target(User $user, array $input): string
    {
        $workspaceId = Fields::text($input['workspaceId'] ?? null, '工作空間', 100, true);
        foreach ($this->listing($user) as $workspace) {
            if ($workspace['id'] === $workspaceId) {
                return $workspaceId;
            }
        }
        DomainError::fail('NOT_FOUND', '找不到可切換的工作空間');
    }
}
