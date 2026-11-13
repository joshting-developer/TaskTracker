<?php

namespace App\Services;

use App\Data\WorkspaceContext;
use App\Data\PageQueryData;
use App\Domain\DomainError;
use App\Models\IssueNotification;
use App\Repositories\ProjectNotificationReadRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 保留既有團隊通知或本人收件權限，不建立Issue收件匣新功能。 */
class ProjectNotificationReadService
{
    /**
     * 注入當次專案授權與通知資料入口。
     *
     * @param  ProjectReadService  $projects  專案資格服務
     * @param  ProjectNotificationReadRepository  $notifications  有界通知投影
     */
    public function __construct(private ProjectReadService $projects, private ProjectNotificationReadRepository $notifications) {}

    /**
     * 取得已授權專案通知摘要，無團隊資格只讀本人收件。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  專案公開ID
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator<IssueNotification> 通知摘要分頁
     */
    public function index(WorkspaceContext $context, string $projectId, PageQueryData $filters): LengthAwarePaginator
    {
        $access = $this->projects->authorized($context, $projectId, 'issue.read');

        return $this->notifications->page($access->project, $context->userId, in_array('notification.readTeam', $access->permissions, true), $filters);
    }

    /**
     * 單筆正文重驗資格，不沿用列表授權快照。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  專案公開ID
     * @param  string  $id  通知公開ID
     * @return IssueNotification 可讀正文
     */
    public function show(WorkspaceContext $context, string $projectId, string $id): IssueNotification
    {
        $access = $this->projects->authorized($context, $projectId, 'issue.read');

        return $this->notifications->find($access->project, $context->userId, in_array('notification.readTeam', $access->permissions, true), $id)
            ?? DomainError::fail('NOT_FOUND', '找不到這筆通知預覽');
    }

    /**
     * TT-315：公開API「我的通知」；只回寄給本人的任務通知, 依位置遞增續讀。
     * 收件人現在已經不能讀該專案的通知會跳過, 但位置照樣往前, 下次不會重讀。
     *
     * @param  WorkspaceContext  $context  Token重驗後的身分與有效權限
     * @param  int|null  $after  上次回傳的nextAfter
     * @param  int  $limit  每次最多掃幾筆（1–100）
     * @return array{items: array<int, IssueNotification>, nextAfter: string|null, hasMore: bool} 通知摘要與下次位置
     */
    public function mine(WorkspaceContext $context, ?int $after, int $limit): array
    {
        $member = $this->notifications->activeMember($context->workspaceId, $context->userId);
        $rows = $member === null ? collect() : $this->notifications->forRecipient($member, $after, $limit + 1);
        $more = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $permissions = $this->projects->permissionsFor($context, $rows->map(fn ($row) => $row->getRelation('project')->public_id)->unique()->values()->all());
        $items = $rows->filter(fn ($row) => in_array('issue.read', $permissions[$row->getRelation('project')->public_id] ?? [], true))->values()->all();
        $last = $rows->last()?->id ?? $after;

        return ['items' => $items, 'nextAfter' => $last === null ? null : (string) $last, 'hasMore' => $more];
    }
}
