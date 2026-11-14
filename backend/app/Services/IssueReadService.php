<?php

namespace App\Services;

use App\Data\IssueOptionQueryData;
use App\Data\IssueQueryData;
use App\Data\PageQueryData;
use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Models\Issue;
use App\Repositories\IssueReadRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 任務讀取的application service；未來Token入口沿用相同permission與scope。 */
class IssueReadService
{
    /**
     * 建立此用例的相依與唯讀資料，不從HTTP取得身分。
     *
     * @param  ProjectReadService  $projects  單Project資格入口
     * @param  IssueReadRepository  $issues  有界卡片讀取
     */
    public function __construct(private ProjectReadService $projects, private IssueReadRepository $issues) {}

    /**
     * 取得當次已授權且有界的列表，不附帶其他頁面的資料。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  IssueQueryData  $filters  有界列表條件
     * @return LengthAwarePaginator<Issue> 有界任務摘要
     */
    public function index(WorkspaceContext $context, string $projectId, IssueQueryData $filters): LengthAwarePaginator
    {
        $project = $this->projects->require($context, $projectId, 'issue.read');
        if (($filters->pagination->page - 1) * $filters->pagination->perPage > IssueQueryData::MAX_LIST_OFFSET) {
            DomainError::fail('INVALID_INPUT', '分頁超過查詢上限, 請縮小篩選範圍或使用異動清單cursor');
        }

        return $this->issues->page($project, $context->userId, $filters, listOnly: true);
    }

    /**
     * 只提供同Project可讀候選；選項不代表寫入授權。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  IssueOptionQueryData  $filters  已驗證選項條件
     * @return LengthAwarePaginator<Issue> 最小候選分頁
     */
    public function options(WorkspaceContext $context, string $projectId, IssueOptionQueryData $filters): LengthAwarePaginator
    {
        return $this->issues->options($this->projects->require($context, $projectId, 'issue.read'), $filters);
    }

    /**
     * 只取得指定資源的資料，保留找不到／無權限語意。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  Issue公開ID
     * @return Issue 已授權任務與最小關聯
     */
    public function show(WorkspaceContext $context, string $projectId, string $id): Issue
    {
        $access = $this->projects->authorized($context, $projectId, 'issue.read');
        // TT-317：已授權issue.read可看該卡片所有有效工時。
        $issue = $this->issues->find($access->project, $id, teamTime: true) ?? DomainError::fail('NOT_FOUND', '找不到這個專案內的任務');
        if ($issue->getRelation('relatedIssues')->count() > 200) {
            DomainError::fail('CONFLICT', '既有關聯超過支援上限，請先整理關聯資料');
        }

        return $issue;
    }

    /**
     * 解析卡號或舊版深連結所屬Project；無權與不存在同樣回應找不到。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $id  Issue公開ID或identifier
     * @return array{id:string,projectId:string} 卡片位置
     */
    public function locate(WorkspaceContext $context, string $id): array
    {
        $location = $this->issues->locate($context->workspaceId, $id) ?? DomainError::fail('NOT_FOUND', '找不到這個任務');
        try {
            $this->projects->authorized($context, $location['projectId'], 'issue.read');
        } catch (DomainError $failure) {
            DomainError::fail('NOT_FOUND', '找不到這個任務');
        }

        return $location;
    }

    /**
     * 只查這個任務的留言分頁，不載入全部活動。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  Issue公開ID
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 留言分頁
     */
    public function comments(WorkspaceContext $context, string $projectId, string $id, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->issues->comments($this->activityIssue($context, $projectId, $id), $filters);
    }

    /**
     * 只查這個任務的異動分頁，維持穩定排序。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  Issue公開ID
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 異動分頁
     */
    public function history(WorkspaceContext $context, string $projectId, string $id, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->issues->history($this->activityIssue($context, $projectId, $id), $filters);
    }

    /**
     * 在關聯tab依單卡種類分頁取得Epic所屬卡或直接子任務。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  Issue公開ID
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator<Issue> 所屬卡摘要分頁
     */
    public function children(WorkspaceContext $context, string $projectId, string $id, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->issues->children($this->activityIssue($context, $projectId, $id), $filters);
    }

    /**
     * 依目前Project工時資格取得單卡或Epic有效明細分頁。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  Issue公開ID
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 有效工時分頁
     *
     * @throws DomainError 缺少卡片讀取權或任務不存在
     */
    public function time(WorkspaceContext $context, string $projectId, string $id, PageQueryData $filters): LengthAwarePaginator
    {
        $access = $this->projects->authorized($context, $projectId, 'issue.read');
        // 僅此卡片活動入口沿issue.read, 月曆／分析仍使用各自權限。
        $issue = $this->issues->reference($access->project, $id) ?? DomainError::fail('NOT_FOUND', '找不到任務');

        return $this->issues->time($issue, $context->userId, team: true, filters: $filters);
    }

    /**
     * 取得當次已授權活動父列，不為活動查詢讀取Issue描述。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  Issue公開ID
     * @return Issue 已授權活動父列
     */
    private function activityIssue(WorkspaceContext $context, string $projectId, string $id): Issue
    {
        $project = $this->projects->require($context, $projectId, 'issue.read');

        return $this->issues->reference($project, $id) ?? DomainError::fail('NOT_FOUND', '找不到這個專案內的任務');
    }
}
