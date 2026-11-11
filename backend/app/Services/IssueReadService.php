<?php

namespace App\Services;

use App\Data\IssueOptionQueryData;
use App\Data\IssueQueryData;
use App\Data\PageQueryData;
use App\Domain\DomainError;
use App\Models\Issue;
use App\Repositories\IssueReadRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 任務讀取的application service；未來Token入口沿用相同permission與scope。 */
class IssueReadService
{
    /** 建立此用例的相依與唯讀資料，不從HTTP取得身分。 */
    public function __construct(private ProjectReadService $projects, private IssueReadRepository $issues) {}

    /**
     * 取得當次已授權且有界的列表，不附帶其他頁面的資料。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  string  $projectId  Project公開ID
     * @param  IssueQueryData  $filters  有界列表條件
     * @return LengthAwarePaginator<Issue> 有界任務摘要
     */
    public function index(array $actor, string $projectId, IssueQueryData $filters): LengthAwarePaginator
    {
        $project = $this->projects->require($actor, $projectId, 'issue.read');

        return $this->issues->page($project, $actor['user']['id'], $filters, listOnly: true);
    }

    /**
     * 只提供同Project可讀候選；選項不代表寫入授權。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  string  $projectId  Project公開ID
     * @param  IssueOptionQueryData  $filters  已驗證選項條件
     * @return LengthAwarePaginator<Issue> 最小候選分頁
     */
    public function options(array $actor, string $projectId, IssueOptionQueryData $filters): LengthAwarePaginator
    {
        return $this->issues->options($this->projects->require($actor, $projectId, 'issue.read'), $filters);
    }

    /**
     * 只取得指定資源的資料，保留找不到／無權限語意。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  Issue公開ID
     * @return Issue 已授權任務與最小關聯
     */
    public function show(array $actor, string $projectId, string $id): Issue
    {
        $access = $this->projects->authorized($actor, $projectId, 'issue.read');
        $own = in_array('timeEntry.readOwn', $access->permissions, true);
        $team = in_array('timeEntry.readTeam', $access->permissions, true);

        $issue = $this->issues->find($access->project, $id, $own ? $actor['user']['id'] : null, $team) ?? DomainError::fail('NOT_FOUND', '找不到這個專案內的任務');
        if ($issue->getRelation('relatedIssues')->count() > 200) {
            DomainError::fail('CONFLICT', '既有關聯超過支援上限，請先整理關聯資料');
        }

        return $issue;
    }

    /**
     * 只查這個任務的留言分頁，不載入全部活動。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  Issue公開ID
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 留言分頁
     */
    public function comments(array $actor, string $projectId, string $id, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->issues->comments($this->activityIssue($actor, $projectId, $id), $filters);
    }

    /**
     * 只查這個任務的異動分頁，維持穩定排序。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  Issue公開ID
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 異動分頁
     */
    public function history(array $actor, string $projectId, string $id, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->issues->history($this->activityIssue($actor, $projectId, $id), $filters);
    }

    /**
     * 在關聯tab依單卡種類分頁取得Epic所屬卡或直接子任務。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  Issue公開ID
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator<Issue> 所屬卡摘要分頁
     */
    public function children(array $actor, string $projectId, string $id, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->issues->children($this->activityIssue($actor, $projectId, $id), $filters);
    }

    /**
     * 依目前Project工時資格取得單卡或Epic有效明細分頁。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  Issue公開ID
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 有效工時分頁
     *
     * @throws DomainError 缺少工時讀取權或任務不存在
     */
    public function time(array $actor, string $projectId, string $id, PageQueryData $filters): LengthAwarePaginator
    {
        $access = $this->projects->authorized($actor, $projectId, 'issue.read');
        $team = in_array('timeEntry.readTeam', $access->permissions, true);
        if (! $team && ! in_array('timeEntry.readOwn', $access->permissions, true)) {
            DomainError::fail('FORBIDDEN', '沒有工時讀取權限');
        }
        $issue = $this->issues->reference($access->project, $id) ?? DomainError::fail('NOT_FOUND', '找不到任務');

        return $this->issues->time($issue, $actor['user']['id'], $team, $filters);
    }

    /**
     * 取得當次已授權活動父列，不為活動查詢讀取Issue描述。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  Issue公開ID
     * @return Issue 已授權活動父列
     */
    private function activityIssue(array $actor, string $projectId, string $id): Issue
    {
        $project = $this->projects->require($actor, $projectId, 'issue.read');

        return $this->issues->reference($project, $id) ?? DomainError::fail('NOT_FOUND', '找不到這個專案內的任務');
    }
}
