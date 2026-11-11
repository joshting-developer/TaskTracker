<?php

namespace App\Services;

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

    /** 取得當次已授權且有界的列表，不附帶其他頁面的資料。 */
    public function index(array $actor, string $projectId, IssueQueryData $filters): LengthAwarePaginator
    {
        $project = $this->projects->require($actor, $projectId, 'issue.read');

        return $this->issues->page($project, $actor['user']['id'], $filters);
    }

    /** 只取得指定資源的資料，保留找不到／無權限語意。 */
    public function show(array $actor, string $projectId, string $id): Issue
    {
        $project = $this->projects->require($actor, $projectId, 'issue.read');

        return $this->issues->find($project, $id) ?? DomainError::fail('NOT_FOUND', '找不到這個專案內的任務');
    }

    /** 只查這個任務的留言分頁，不載入全部活動。 */
    public function comments(array $actor, string $projectId, string $id, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->issues->comments($this->activityIssue($actor, $projectId, $id), $filters);
    }

    /** 只查這個任務的異動分頁，維持穩定排序。 */
    public function history(array $actor, string $projectId, string $id, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->issues->history($this->activityIssue($actor, $projectId, $id), $filters);
    }

    /** 取得當次已授權活動父列，不為活動查詢讀取Issue描述。 */
    private function activityIssue(array $actor, string $projectId, string $id): Issue
    {
        $project = $this->projects->require($actor, $projectId, 'issue.read');

        return $this->issues->reference($project, $id) ?? DomainError::fail('NOT_FOUND', '找不到這個專案內的任務');
    }
}
