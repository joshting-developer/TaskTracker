<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Models\IssueNotification;
use App\Models\Project;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** 依已授權Project與收件人投影通知預覽，列表不讀正文。 */
class ProjectNotificationReadRepository
{
    /**
     * 在SQL限制Project及收件者，管理資格由Service先解析。
     *
     * @param  Project  $project  已授權專案
     * @param  string  $userId  可信使用者ID
     * @param  bool  $team  是否具團隊通知權限
     * @return Builder<IssueNotification> 限定查詢
     */
    private function scope(Project $project, string $userId, bool $team): Builder
    {
        return IssueNotification::query()->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)
            ->when(! $team, fn ($q) => $q->whereHas('recipient', fn ($q) => $q->where('user_id', $userId)->whereNull('disabled_at')));
    }

    /**
     * 穩定倒序分頁，只預載最小卡片識別。
     *
     * @param  Project  $project  已授權專案
     * @param  string  $userId  可信使用者ID
     * @param  bool  $team  是否具團隊通知權限
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator<IssueNotification> 通知摘要
     */
    public function page(Project $project, string $userId, bool $team, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->scope($project, $userId, $team)->with('issue:id,public_id,identifier')
            ->orderByDesc('created_at')->orderBy('public_id')
            ->paginate($filters->perPage, ['id', 'public_id', 'issue_id', 'subject', 'email', 'status', 'kind', 'created_at'], 'page', $filters->page);
    }

    /**
     * 單筆正文按需讀取，跨專案或非收件人不會命中。
     *
     * @param  Project  $project  已授權專案
     * @param  string  $userId  可信使用者ID
     * @param  bool  $team  是否具團隊通知權限
     * @param  string  $id  通知公開ID
     * @return IssueNotification|null 有效通知
     */
    public function find(Project $project, string $userId, bool $team, string $id): ?IssueNotification
    {
        return $this->scope($project, $userId, $team)->where('public_id', $id)->with('issue:id,public_id,identifier')
            ->first(['id', 'public_id', 'issue_id', 'subject', 'email', 'body', 'status', 'kind', 'created_at']);
    }

    /**
     * TT-315：找出使用者在這個工作空間目前有效的成員身分, 停用的不算。
     *
     * @param  string  $workspaceId  工作空間公開ID
     * @param  string  $userId  可信使用者ID
     * @return WorkspaceMember|null 有效成員
     */
    public function activeMember(string $workspaceId, string $userId): ?WorkspaceMember
    {
        return WorkspaceMember::query()->whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))
            ->where('user_id', $userId)->whereNull('disabled_at')->first(['id', 'workspace_id']);
    }

    /**
     * TT-315：寄給這位成員的通知, 依內部id遞增, 從after之後讀；走(workspace_id, recipient_id)外鍵索引, 不讀正文。
     *
     * @param  WorkspaceMember  $member  有效收件成員
     * @param  int|null  $after  上次讀到的位置
     * @param  int  $take  最多取幾筆（呼叫端多取一筆判斷還有沒有）
     * @return Collection<int, IssueNotification> 通知摘要
     */
    public function forRecipient(WorkspaceMember $member, ?int $after, int $take): Collection
    {
        return IssueNotification::query()->where('workspace_id', $member->workspace_id)->where('recipient_id', $member->id)
            ->when($after !== null, fn ($q) => $q->where('id', '>', $after))
            ->with(['issue:id,public_id,identifier', 'project:id,public_id'])
            ->orderBy('id')->limit($take)
            ->get(['id', 'public_id', 'project_id', 'issue_id', 'subject', 'kind', 'created_at']);
    }
}
