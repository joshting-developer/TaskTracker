<?php

namespace App\Repositories;

use App\Data\IssueLinkData;
use App\Domain\DomainError;
use App\Domain\Fields;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 保留領域DTO入口；正常業務只使用關聯式表，舊JSON僅供遷移與歷史相容。 */
class RecordStore
{
    private bool $relational;

    private RelationalRecords $records;

    private LegacyRecordStore $archive;

    /** 選擇伺服器限定的Workspace，歷史migration可明確指定舊格式。
     * @param  ?string  $workspaceId  工作空間公開ID
     * @param  bool  $legacy  是否為歷史遷移／匯入階段
     * @return void
     */
    public function __construct(private ?string $workspaceId = null, private bool $legacy = false)
    {
        $this->relational = ! $legacy && Schema::hasTable('workspaces');
        $this->records = new RelationalRecords($workspaceId);
        $this->archive = $workspaceId === null ? new LegacyRecordStore : (new LegacyRecordStore)->forWorkspace($workspaceId);
    }

    /** 建立同一儲存模式的Workspace資料入口。
     * @param  string  $id  工作空間公開ID
     * @return self
     */
    public function forWorkspace(string $id): self
    {
        return new self($id, $this->legacy);
    }

    /** 讀取必要紀錄，保留原API錯誤。
     * @param  string  $kind  實體種類
     * @param  string  $id  公開ID
     * @return array
     */
    public function require(string $kind, string $id): array
    {
        return $this->get($kind, $id) ?? DomainError::fail('NOT_FOUND', '找不到這筆資料, 請重新載入');
    }

    /** 讀取單筆租戶資料。
     * @param  string  $kind  實體種類
     * @param  string  $id  公開ID
     * @return ?array
     */
    public function get(string $kind, string $id): ?array
    {
        if (preg_match('/^w:(.+):([A-Za-z]+)$/D', $kind, $parts)) {
            return $this->forWorkspace($parts[1])->get($parts[2], $id);
        }

        return $this->usesRelational($kind) ? $this->records->get($kind, $id) : $this->archive->get($kind, $id);
    }

    /** 按原插入順序讀取同租戶的指定種類。
     * @param  string  $kind  實體種類
     * @return array
     */
    public function all(string $kind): array
    {
        return $this->usesRelational($kind) ? $this->records->all($kind) : $this->archive->all($kind);
    }

    /**
     * 相容入口的批次參照投影；不擴大租戶或代替領域授權。
     *
     * @param  array  $ids  卡片公開ID
     * @return array<string, string>
     */
    public function issueProjects(array $ids): array
    {
        if ($this->usesRelational('issues')) {
            return $this->records->issueProjects($ids);
        }

        return array_column(array_filter($this->all('issues'), fn ($issue) => in_array($issue['id'], $ids, true)), 'projectId', 'id');
    }

    /** 取得關聯驗證投影；舊格式只供歷史遷移，不用於正常API。
     * @param  array<string>  $ids  明確指定的卡片ID
     * @param  string  $currentId  目前卡片ID，建立時為空
     * @return array<string, IssueLinkData>
     */
    public function issueLinks(array $ids, string $currentId = ''): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($this->usesRelational('issues')) {
            return $this->records->issueLinks($ids, $currentId);
        }
        $result = [];
        foreach ($ids as $id) {
            $issue = $this->get('issues', $id);
            if ($issue) {
                $result[$id] = new IssueLinkData($id, $issue['projectId'], $issue['type'], $issue['parentIssueId'] ?? '',
                    $issue['epicId'] ?? '', count($issue['relatedIssueIds'] ?? []), in_array($currentId, $issue['relatedIssueIds'] ?? [], true));
            }
        }

        return $result;
    }

    /** 用SQL exists檢查目前專案的必要子集合。
     * @param  string  $projectId  專案公開ID
     * @param  string  $issueId  父項公開ID
     * @param  bool  $epic  是否檢查Epic所屬卡片
     * @param  bool  $unclosed  是否只查未結案卡片
     * @return bool
     */
    public function issueHasChildren(string $projectId, string $issueId, bool $epic = false, bool $unclosed = false): bool
    {
        if ($this->usesRelational('issues')) {
            return $this->records->issueHasChildren($projectId, $issueId, $epic, $unclosed);
        }
        foreach ($this->all('issues') as $issue) {
            if ($issue['projectId'] === $projectId && ($issue[$epic ? 'epicId' : 'parentIssueId'] ?? '') === $issueId
                && (! $unclosed || ! Fields::isClosed($issue))) {
                return true;
            }
        }

        return false;
    }

    /** 只讀取指定卡片有效工時的存在性。
     * @param  string  $projectId  專案公開ID
     * @param  string  $issueId  任務公開ID
     * @return bool
     */
    public function issueHasActiveTime(string $projectId, string $issueId): bool
    {
        if ($this->usesRelational('timeEntries')) {
            return $this->records->issueHasActiveTime($projectId, $issueId);
        }

        return (bool) array_filter($this->all('timeEntries'), fn ($entry) => $entry['issueId'] === $issueId && empty($entry['voidedAt']));
    }

    /** 逐批提供實際需要同步的子項識別碼。
     * @param  string  $projectId  專案公開ID
     * @param  string  $issueId  父項公開ID
     * @return \Generator<int, string>
     */
    public function issueChildIds(string $projectId, string $issueId): \Generator
    {
        if ($this->usesRelational('issues')) {
            yield from $this->records->issueChildIds($projectId, $issueId);

            return;
        }
        foreach ($this->all('issues') as $issue) {
            if ($issue['projectId'] === $projectId && ($issue['parentIssueId'] ?? '') === $issueId) {
                yield $issue['id'];
            }
        }
    }

    /**
     * 交易鎖與資格已由上層確認；這裡只負責當人當日SQL聚合。
     *
     * @param  string  $userId  填報者User ID
     * @param  string  $date  工作日（Asia/Taipei）
     * @param  string  $excludeId  要排除的工時公開ID（修改時排除自己）
     * @return int 當日有效工時分鐘數
     */
    public function dailyMinutes(string $userId, string $date, string $excludeId = ''): int
    {
        if ($this->usesRelational('timeEntries')) {
            return $this->records->dailyMinutes($userId, $date, $excludeId);
        }

        return array_sum(array_column(array_filter($this->all('timeEntries'), fn ($entry) => $entry['userId'] === $userId
            && $entry['workDate'] === $date && empty($entry['voidedAt']) && $entry['id'] !== $excludeId), 'minutes'));
    }

    /** 保存到真正的欄位及子表；正常模式不回寫JSON。
     * @param  string  $kind  實體種類
     * @param  string  $id  公開ID
     * @param  array  $value  已驗證DTO
     * @return void
     */
    public function put(string $kind, string $id, array $value): void
    {
        if (preg_match('/^w:(.+):([A-Za-z]+)$/D', $kind, $parts)) {
            $this->forWorkspace($parts[1])->put($parts[2], $id, $value);

            return;
        }
        if (! $this->relational) {
            $this->archive->put($kind, $id, $value);

            return;
        }
        if (! $this->usesRelational($kind)) {
            throw new \LogicException('正常業務不可寫入舊JSON種類：'.$kind);
        }
        $this->records->put($kind, $id, $value);
    }

    /** 判斷是否有可使用的關聯式實體；無scope資料不跨租戶讀取。
     * @param  string  $kind  實體種類
     * @return bool
     */
    private function usesRelational(string $kind): bool
    {
        $definition = EntityDefinitions::ENTITIES[$kind] ?? null;

        return $this->relational && $definition && ($this->workspaceId !== null || in_array($definition['scope'], [null, 'global'], true));
    }

    /** 只重設明確選定的Seed空間內容，依外鍵順序刪除。
     * @return void
     */
    public function clearWorkspace(): void
    {
        if ($this->workspaceId === null) {
            throw new \LogicException('重設必須明確指定Workspace');
        }
        if (! $this->relational) {
            DB::table('records')->where('kind', 'like', 'w:'.$this->workspaceId.':%')->delete();

            return;
        }
        $id = DB::table('workspaces')->where('public_id', $this->workspaceId)->value('id');
        if ($id === null) {
            return;
        }
        foreach (['audit_events', 'todo_notifications', 'todos', 'issue_notifications', 'time_entries', 'board_issues', 'boards', 'issue_relations'] as $table) {
            DB::table($table)->where('workspace_id', $id)->delete();
        }
        // 留言跟隨任務cascade；父子與Epic先刪除葉節點，避免繞過外鍵。
        DB::table('issues')->where('workspace_id', $id)->whereNotNull('parent_issue_id')->delete();
        DB::table('issues')->where('workspace_id', $id)->where('type', '!=', 'epic')->delete();
        DB::table('issues')->where('workspace_id', $id)->delete();
        foreach (['project_members', 'project_access_controls', 'project_statuses', 'projects', 'workspace_upgrades', 'retired_saved_views', 'operation_receipts', 'todo_operation_receipts', 'workspace_members'] as $table) {
            DB::table($table)->where('workspace_id', $id)->delete();
        }
    }
}
