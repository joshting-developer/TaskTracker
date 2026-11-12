<?php

namespace App\Services;

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
     * @param  array<string,mixed>  $actor  可信操作者
     * @param  string  $projectId  專案公開ID
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator<IssueNotification> 通知摘要分頁
     */
    public function index(array $actor, string $projectId, PageQueryData $filters): LengthAwarePaginator
    {
        $access = $this->projects->authorized($actor, $projectId, 'issue.read');

        return $this->notifications->page($access->project, $actor['user']['id'], in_array('notification.readTeam', $access->permissions, true), $filters);
    }

    /**
     * 單筆正文重驗資格，不沿用列表授權快照。
     *
     * @param  array<string,mixed>  $actor  可信操作者
     * @param  string  $projectId  專案公開ID
     * @param  string  $id  通知公開ID
     * @return IssueNotification 可讀正文
     */
    public function show(array $actor, string $projectId, string $id): IssueNotification
    {
        $access = $this->projects->authorized($actor, $projectId, 'issue.read');

        return $this->notifications->find($access->project, $actor['user']['id'], in_array('notification.readTeam', $access->permissions, true), $id)
            ?? DomainError::fail('NOT_FOUND', '找不到這筆通知預覽');
    }
}
