<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Repositories\RecordStore;
use Illuminate\Support\Str;

/**
 * 管理專案看板收錄與版本，移除看板不刪除卡片。
 */
class ProjectBoards
{
    /**
     * 設定此服務使用的資料入口與可信任相依。
     *
     * @param  RecordStore  $store  限定工作空間的資料存取入口
     * @param  array  $workspaceSession  從後端Session重新驗證的工作空間身分與權限
     * @return void
     */
    public function __construct(private RecordStore $store, private array $workspaceSession) {}

    /**
     * 回傳Default看板與尚未移除的自訂看板。
     *
     * @return array
     */
    public function listing(): array
    {
        $defaults = array_map(fn ($project) => [
            'id' => 'default',
            'projectId' => $project['id'],
            'name' => 'Default',
            'selectedIssueIds' => [],
            'isDefault' => true,
            'revision' => 0,
        ], $this->store->all('projects'));

        return [...$defaults, ...array_values(array_filter($this->store->all('boards'), fn ($board) => empty($board['archivedAt'])))];
    }

    /**
     * 驗證看板名稱及明確收錄的同專案卡片。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @param  string  $projectId  所屬專案識別碼
     * @return array
     */
    private function fields(array $input, string $projectId): array
    {
        $this->store->require('projects', $projectId);
        $ids = $input['selectedIssueIds'] ?? [];
        if (! is_array($ids) || ! array_is_list($ids) || count($ids) > 1000) {
            DomainError::fail('INVALID_INPUT', '看板最多明確收錄 1000 個項目');
        }
        $ids = array_map(fn ($id) => Fields::text($id, '收錄項目', 100, true), $ids);
        if (count(array_unique($ids)) !== count($ids)) {
            DomainError::fail('INVALID_INPUT', '收錄項目不可重複');
        }
        foreach ($ids as $id) {
            $issue = $this->store->require('issues', $id);
            if ($issue['projectId'] !== $projectId) {
                DomainError::fail('INVALID_INPUT', '只能收錄目前專案的 Epic、Task 或 Bug');
            }
        }
        sort($ids);

        return [
            'name' => Fields::text($input['name'] ?? null, '看板名稱', 60, true),
            'selectedIssueIds' => $ids,
        ];
    }

    /**
     * 取得可修改且版本正確的自訂看板。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    private function current(array $input): array
    {
        if (($input['id'] ?? '') === 'default') {
            DomainError::fail('INVALID_INPUT', 'Default 是固定看板, 不可修改或移除');
        }
        $board = $this->store->require('boards', Fields::text($input['id'] ?? null, '看板', 100, true));
        $this->store->require('projects', $board['projectId']);
        if (! empty($board['archivedAt'])) {
            DomainError::fail('NOT_FOUND', '看板已移除');
        }
        if (($input['expectedRevision'] ?? null) !== $board['revision']) {
            DomainError::fail('REVISION_CONFLICT', '看板已被更新, 請關閉重開後再修改');
        }
        if (isset($input['projectId']) && $input['projectId'] !== $board['projectId']) {
            DomainError::fail('INVALID_INPUT', '不可變更看板所屬專案');
        }

        return $board;
    }

    /**
     * 建立目前專案的自訂看板。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function create(array $input): array
    {
        $projectId = Fields::text($input['projectId'] ?? null, '專案', 100, true);
        $board = [
            'id' => (string) Str::uuid(),
            'projectId' => $projectId,
            ...$this->fields($input, $projectId),
            'isDefault' => false,
            'revision' => 1,
            'createdBy' => $this->workspaceSession['user']['id'],
            'createdAt' => now()->utc()->toISOString(),
            'updatedAt' => now()->utc()->toISOString(),
        ];
        $this->store->put('boards', $board['id'], $board);

        return $board;
    }

    /**
     * 驗證版本後更新看板名稱與收錄。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function update(array $input): array
    {
        $board = $this->current($input);
        $board = [
            ...$board,
            ...$this->fields($input, $board['projectId']),
            'revision' => $board['revision'] + 1,
            'updatedAt' => now()->utc()->toISOString(),
        ];
        $this->store->put('boards', $board['id'], $board);

        return $board;
    }

    /**
     * 標記看板移除並保留卡片資料。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function archive(array $input): array
    {
        $board = $this->current($input);
        $this->store->put('boards', $board['id'], [
            ...$board,
            'archivedAt' => now()->utc()->toISOString(),
            'revision' => $board['revision'] + 1,
        ]);

        return ['id' => $board['id']];
    }

    /**
     * 以相同交易將新卡加入有管理權限的看板。
     *
     * @param  string  $boardId  收錄新卡的看板識別碼
     * @param  array  $issue  目前任務內容
     * @return void
     */
    public function includeCreatedIssue(string $boardId, array $issue): void
    {
        (new ProjectAccess($this->store, $this->workspaceSession))->require($issue['projectId'], 'board.manage');
        $board = $this->store->require('boards', $boardId);
        if (! empty($board['archivedAt']) || $board['projectId'] !== $issue['projectId']) {
            DomainError::fail('NOT_FOUND', '找不到這個專案的看板');
        }
        if (in_array($issue['epicId'], $board['selectedIssueIds'], true)) {
            return;
        }
        if (count($board['selectedIssueIds']) >= 1000) {
            DomainError::fail('INVALID_INPUT', '看板明確收錄已達上限');
        }
        $this->store->put('boards', $boardId, [
            ...$board,
            'selectedIssueIds' => [...$board['selectedIssueIds'], $issue['id']],
            'revision' => $board['revision'] + 1,
            'updatedAt' => now()->utc()->toISOString(),
        ]);
    }
}
