<?php

namespace App\Services;

use App\Data\WorkspaceContext;
use App\Data\IssueQueryData;
use App\Data\PageQueryData;
use App\Domain\DomainError;
use App\Models\Board;
use App\Models\Project;
use App\Repositories\BoardReadRepository;
use App\Repositories\IssueReadRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 以當次可信資格驗證Project及看板，不保存跨請求租戶狀態。 */
class BoardReadService
{
    /**
     * 注入查詢與授權協作者。
     *
     * @param  ProjectReadService  $projects  單Project資格入口
     * @param  BoardReadRepository  $boards  有界看板讀取
     * @param  IssueReadRepository  $issues  有界卡片讀取
     */
    public function __construct(private ProjectReadService $projects, private BoardReadRepository $boards, private IssueReadRepository $issues) {}

    /**
     * 回傳自訂看板分頁及獨立Default摘要，不傳任何卡片內容。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  PageQueryData  $filters  有界條件
     * @return array{page:LengthAwarePaginator,defaultBoard:array<string,mixed>} 看板摘要
     */
    public function index(WorkspaceContext $context, string $projectId, PageQueryData $filters): array
    {
        $project = $this->projects->require($context, $projectId, 'issue.read');

        return ['page' => $this->boards->page($project, $filters), 'defaultBoard' => $this->summary($project, null)];
    }

    /**
     * 讀取單看板屬性，編輯收錄另以明確上限提供ID。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  看板公開ID或default
     * @return array<string,mixed> 看板屬性與明確收錄
     */
    public function show(WorkspaceContext $context, string $projectId, string $id): array
    {
        $project = $this->projects->require($context, $projectId, 'issue.read');
        $board = $this->find($project, $id);

        return [...$this->summary($project, $board), 'selectedIssueIds' => $board ? $this->boards->selectedIds($board) : []];
    }

    /**
     * 將篩選與看板收錄在SQL交集後分頁。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  看板公開ID或default
     * @param  IssueQueryData  $filters  有界任務條件
     * @return LengthAwarePaginator 卡片摘要分頁
     */
    public function issues(WorkspaceContext $context, string $projectId, string $id, IssueQueryData $filters): LengthAwarePaginator
    {
        $access = $this->projects->authorized($context, $projectId, 'issue.read');
        $project = $access->project;
        $board = $this->find($project, $id);
        $team = in_array('timeEntry.readTeam', $access->permissions, true);
        $own = in_array('timeEntry.readOwn', $access->permissions, true);

        return $this->issues->page($project, $context->userId, $filters, $board?->id, $own ? $context->userId : null, $team);
    }

    /**
     * 輕量變更檢查：回傳看板畫面相依資料的不透明版本，不含卡片、成員或工時內容。
     * 每次都重新驗證issue.read；撤權／封存仍回FORBIDDEN或NOT_FOUND，不讓未授權者探測資源是否更新。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分、工作區與有效權限
     * @param  string  $projectId  Project公開ID
     * @param  string  $id  看板公開ID或default
     * @return array{projectId:string,boardId:string,version:string} 版本摘要
     */
    public function version(WorkspaceContext $context, string $projectId, string $id): array
    {
        $access = $this->projects->authorized($context, $projectId, 'issue.read');
        $board = $this->find($access->project, $id);
        $team = in_array('timeEntry.readTeam', $access->permissions, true);
        $own = in_array('timeEntry.readOwn', $access->permissions, true);
        $permissions = $access->permissions;
        sort($permissions);
        $parts = $this->boards->versionParts($access->project, $own && ! $team ? $context->userId : null, $team);

        return ['projectId' => $access->project->public_id, 'boardId' => $board?->public_id ?? 'default',
            'version' => hash('sha256', json_encode([$parts, $board?->revision ?? 0, $permissions], JSON_THROW_ON_ERROR))];
    }

    /**
     * 保留Default虛擬看板，其他ID必須同Project且未封存。
     *
     * @param  Project  $project  已授權Project
     * @param  string  $id  看板公開ID
     * @return Board|null null僅代表Default
     *
     * @throws DomainError 無法存取看板
     */
    private function find(Project $project, string $id): ?Board
    {
        if ($id === 'default') {
            return null;
        }

        return $this->boards->find($project, $id) ?? DomainError::fail('NOT_FOUND', '找不到這個專案內的看板');
    }

    /**
     * 建立摘要資料，不包含卡片或歷史。
     *
     * @param  Project  $project  已授權Project
     * @param  Board|null  $board  null代表Default
     * @return array<string,mixed> 有界摘要
     */
    private function summary(Project $project, ?Board $board): array
    {
        return ['id' => $board?->public_id ?? 'default', 'projectId' => $project->public_id,
            'name' => $board?->name ?? 'Default', 'isDefault' => $board === null,
            'revision' => $board?->revision ?? 0, ...$this->boards->count($project, $board)];
    }
}
