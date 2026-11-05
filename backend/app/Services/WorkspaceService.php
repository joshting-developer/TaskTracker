<?php

namespace App\Services;

use App\Authorization\WorkspaceOperationAccess;
use App\Domain\DomainError;
use App\Repositories\RecordStore;

/**
 * 維持既有操作契約，協調逐筆授權、重試與各領域寫入。
 */
class WorkspaceService
{
    private RecordStore $store;

    private WorkspaceOperationAccess $authorization;

    /**
     * 設定此服務使用的資料入口與可信任相依。
     *
     * @param  array  $workspaceSession  從後端Session重新驗證的工作空間身分與權限
     * @return void
     */
    public function __construct(private array $workspaceSession)
    {
        $this->store = (new RecordStore)->forWorkspace($workspaceSession['workspace']['id']);
        $this->authorization = new WorkspaceOperationAccess($this->store, $workspaceSession);
    }

    /**
     * 驗證操作邊界並執行查詢或受保護的寫入。
     *
     * @param  string  $operation  已允許的操作名稱
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function execute(string $operation, array $input): array
    {
        $this->authorization->authorize($operation, $input);
        if ($operation === 'getTimeReport') {
            return (new WorkspaceTimeReport($this->store, $this->workspaceSession))->get($input);
        }
        if ($operation === 'getWorkspace') {
            return (new WorkspaceQuery($this->store, $this->workspaceSession))->getWorkspace();
        }
        if ($operation === 'getProjectMembers') {
            return (new ProjectMembers($this->store, $this->workspaceSession))->listing($input['projectId']);
        }

        return (new RequestReplay)->execute($this->store, 'requests', $this->workspaceSession['user']['id'], $operation, $input, fn () => $this->write($operation, $input));
    }

    /**
     * 以明確操作映射呼叫對應領域服務。
     *
     * @param  string  $operation  已允許的操作名稱
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    private function write(string $operation, array $input): array
    {
        $projects = new ProjectService($this->store, $this->workspaceSession);
        $issues = new IssueService($this->store, $this->workspaceSession);
        $time = new TimeEntryService($this->store, $this->workspaceSession);
        $boards = new ProjectBoards($this->store, $this->workspaceSession);
        $members = new ProjectMembers($this->store, $this->workspaceSession);

        $result = match ($operation) {
            'createProject' => $projects->createProject($input),
            'updateProject' => $projects->updateProject($input),
            'updateStatuses' => $projects->updateStatuses($input),
            'createIssue' => $issues->createIssue($input),
            'updateIssue' => $issues->updateIssue($input),
            'addComment' => $issues->addComment($input),
            'logTime' => $time->logTime($input),
            'updateTime' => $time->updateTime($input),
            'voidTime' => $time->voidTime($input),
            'createBoard' => $boards->create($input),
            'updateBoard' => $boards->update($input),
            'archiveBoard' => $boards->archive($input),
            'setProjectMember' => $members->change($input, false),
            'removeProjectMember' => $members->change($input, true),
            'loadDemo' => (new DemoService($this->store, $this->workspaceSession, $projects, $issues))->loadDemo($input),
            default => DomainError::fail('NOT_FOUND', '找不到這個操作'),
        };
        $projectId = match ($operation) {
            'createProject', 'updateProject', 'updateStatuses' => $result['id'],
            'archiveBoard' => $this->store->require('boards', $result['id'])['projectId'],
            default => $result['projectId'] ?? '',
        };
        (new ProjectSignals($this->workspaceSession))->changed($projectId);

        return $result;
    }
}
