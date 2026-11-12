<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Models\IssueNotification;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
            ->paginate($filters->perPage, ['id', 'public_id', 'issue_id', 'subject', 'email', 'status', 'created_at'], 'page', $filters->page);
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
            ->first(['id', 'public_id', 'issue_id', 'subject', 'email', 'body', 'status', 'created_at']);
    }
}
