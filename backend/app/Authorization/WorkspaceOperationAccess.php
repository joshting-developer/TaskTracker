<?php

namespace App\Authorization;

use App\Authorization\WorkspacePermissions as Permissions;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Repositories\RecordStore;

/**
 * 在讀取重試結果前驗證操作permission、Project scope與作者條件。
 */
class WorkspaceOperationAccess
{
    /**
     * 設定此服務使用的資料入口與可信任相依。
     *
     * @param  RecordStore  $store  限定工作空間的資料存取入口
     * @param  array  $workspaceSession  從後端Session重新驗證的工作空間身分與權限
     * @param  ProjectAccess  $access  本次操作共用的Project權限檢查
     */
    public function __construct(private RecordStore $store, private array $workspaceSession, private ProjectAccess $access) {}

    /**
     * 檢查有效permission，拒絕未授權操作。
     *
     * @param  string  $operation  已允許的操作名稱
     * @param  array  $input  此次操作的原始輸入欄位
     * @return void
     */
    public function authorize(string $operation, array $input): void
    {
        Permissions::authorize($this->workspaceSession, 'workspace.read');
        if ($operation === 'getTimeReport') {
            Permissions::authorize($this->workspaceSession, 'workspace.timeReports.read');

            return;
        }
        if ($operation === 'getWorkspace') {
            return;
        }
        if (in_array($operation, ['createProject', 'loadDemo'], true)) {
            Permissions::authorize($this->workspaceSession, 'project.create');

            return;
        }
        [$kind, $key, $permission] = match ($operation) {
            'createIssue' => ['projects', 'projectId', 'issue.write'],
            'updateIssue', 'addComment' => ['issues', 'id', 'issue.write'],
            // 刪卡以Project層級授權，卡片刪除後同requestId重播仍能先授權再回原結果；卡片所屬由Service核對。
            'deleteIssue' => ['projects', 'projectId', 'issue.delete'],
            'updateProject' => ['projects', 'id', 'project.update'],
            'updateStatuses' => ['projects', 'projectId', 'project.statuses.update'],
            'createBoard' => ['projects', 'projectId', 'board.manage'],
            'updateBoard', 'archiveBoard', 'restoreBoard' => ['boards', 'id', 'board.manage'],
            'logTime' => ['issues', 'issueId', 'timeEntry.writeOwn'],
            'updateTime', 'voidTime' => ['timeEntries', 'id', 'timeEntry.writeOwn'],
            'getProjectMembers', 'setProjectMember', 'removeProjectMember' => ['projects', 'projectId', 'project.members.manage'],
            default => DomainError::fail('NOT_FOUND', '找不到這個操作'),
        };
        if ($kind === 'boards' && ($input[$key] ?? '') === 'default') {
            DomainError::fail('INVALID_INPUT', 'Default 是固定看板, 不可修改或移除');
        }
        $resource = $this->store->require($kind, Fields::text($input[$key] ?? null, '資料識別碼', 100, true));
        $projectId = $kind === 'projects' ? $resource['id'] : $resource['projectId'];
        $this->access->require($projectId, $permission);
        if ($kind === 'timeEntries' && $resource['userId'] !== $this->workspaceSession['user']['id']) {
            DomainError::fail('FORBIDDEN', '只能修改自己的工時');
        }
        if ($operation === 'createIssue' && ! empty($input['boardId']) && $input['boardId'] !== 'default') {
            $this->access->require($projectId, 'board.manage');
        }
        // 同一 requestId 的快取也必須在當次資源授權後才讀取
    }
}
