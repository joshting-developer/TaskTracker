<?php

namespace App\Repositories;

use App\Data\IssueLinkData;
use App\Database\DatabaseTimes;
use App\Domain\DomainError;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** 將既有API DTO映射到真正欄位與外鍵，業務資料不再寫入records payload。 */
class RelationalRecords
{
    private array $identities = [];

    private ?int $workspaceInternalId = null;

    private bool $workspaceResolved = false;

    private array $users = [];

    private array $states = [];

    private ?bool $hasTodoContent = null;

    private RelationalChildren $children;

    /** 設定由伺服器選定的Workspace公開ID。
     * @param  ?string  $workspacePublicId  工作空間公開ID
     * @return void
     */
    public function __construct(private ?string $workspacePublicId = null)
    {
        $this->children = new RelationalChildren;
    }

    /** 建立限制種類與Workspace的查詢。
     * @param  string  $kind  已知實體種類
     * @return Builder
     */
    private function query(string $kind): Builder
    {
        $definition = EntityDefinitions::ENTITIES[$kind];
        $query = $definition['model']::query();
        if (! in_array($definition['scope'], [null, 'global'], true)) {
            $query->where('workspace_id', $this->workspaceId());
        }

        return $query;
    }

    /** 取得Workspace內部主鍵；不存在時讀取不會擴大到其他租戶。
     * @return ?int
     */
    private function workspaceId(): ?int
    {
        if (! $this->workspaceResolved) {
            $this->workspaceInternalId = Workspace::where('public_id', $this->workspacePublicId)->value('id');
            $this->workspaceResolved = true;
        }

        return $this->workspaceInternalId;
    }

    /** 取得種類的公開識別欄位。
     * @param  string  $kind  實體種類
     * @return string
     */
    private function publicColumn(string $kind): string
    {
        return $kind === 'members' ? 'user_id' : 'public_id';
    }

    /** 查詢單筆並批次預載其子集合。
     * @param  string  $kind  實體種類
     * @param  string  $id  公開ID
     * @return ?array
     */
    public function get(string $kind, string $id): ?array
    {
        $this->users = [];
        $this->states = [];
        $row = $this->query($kind)->where($this->publicColumn($kind), $id)->first();
        if (! $row) {
            return null;
        }
        $this->prepareRead($kind, [$row]);

        return $this->serialize($kind, $row);
    }

    /** 按插入順序讀取同一租戶資料並批次預載子表。
     * @param  string  $kind  實體種類
     * @return array
     */
    public function all(string $kind): array
    {
        $this->users = [];
        $this->states = [];
        $query = $this->query($kind);
        if ($kind === 'issues') {
            $query->orderBy('storage_position');
        }
        $rows = $query->orderBy('id')->get();
        $this->prepareRead($kind, $rows->all());

        return $rows->map(fn ($row) => $this->serialize($kind, $row))->all();
    }

    /**
     * 批次取得同工作區任務所屬Project，不載入內容／留言／歷史；資格由Service判斷。
     *
     * @param  array  $ids  卡片公開ID
     * @return array<string, string>
     */
    public function issueProjects(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('issues')->join('projects', 'projects.id', '=', 'issues.project_id')
            ->where('issues.workspace_id', $this->workspaceId())
            ->whereColumn('projects.workspace_id', 'issues.workspace_id')
            ->whereIn('issues.public_id', $ids)
            ->get(['projects.public_id as project_public_id', 'issues.public_id as issue_public_id'])
            ->pluck('project_public_id', 'issue_public_id')->all();
    }

    /** 批次讀指定ID的關聯摘要，反向容量只用SQL聚合，不載入活動與其他卡片。
     * @param  array<string>  $ids  最多父項、Epic與200個關聯ID
     * @param  string  $currentId  目前卡片ID，建立時為空
     * @return array<string, IssueLinkData>
     */
    public function issueLinks(array $ids, string $currentId): array
    {
        if (! $ids) {
            return [];
        }
        $rows = DB::table('issues as target')
            ->join('projects', 'projects.id', '=', 'target.project_id')
            ->leftJoin('issues as parent', 'parent.id', '=', 'target.parent_issue_id')
            ->leftJoin('issues as epic', 'epic.id', '=', 'target.epic_id')
            ->where('target.workspace_id', $this->workspaceId())->whereIn('target.public_id', $ids)
            ->select(['target.public_id', 'projects.public_id as project_public_id', 'target.type', 'parent.public_id as parent_public_id', 'epic.public_id as epic_public_id'])
            ->selectSub(DB::table('issue_relations')->whereColumn('issue_relations.issue_id', 'target.id')->whereColumn('issue_relations.workspace_id', 'target.workspace_id')->selectRaw('COUNT(*)'), 'related_count')
            ->selectSub(DB::table('issue_relations')->join('issues as related', 'related.id', '=', 'issue_relations.related_issue_id')
                ->whereColumn('issue_relations.issue_id', 'target.id')->whereColumn('issue_relations.workspace_id', 'target.workspace_id')
                ->where('related.public_id', $currentId)->selectRaw('COUNT(*)'), 'already_related')->get();
        $result = [];
        foreach ($rows as $row) {
            $result[$row->public_id] = new IssueLinkData($row->public_id, $row->project_public_id, $row->type,
                $row->parent_public_id ?? '', $row->epic_public_id ?? '', (int) $row->related_count, (bool) $row->already_related);
        }

        return $result;
    }

    /** 限定Workspace／Project與父項的子集合查詢；欄位只由內部呼叫者指定。
     * @param  string  $projectId  專案公開ID
     * @param  string  $issueId  父項公開ID
     * @param  string  $column  內部指定的parent_issue_id或epic_id
     * @return \Illuminate\Database\Query\Builder
     */
    private function issueChildrenQuery(string $projectId, string $issueId, string $column): \Illuminate\Database\Query\Builder
    {
        return DB::table('issues')->join('projects', 'projects.id', '=', 'issues.project_id')
            ->where('issues.workspace_id', $this->workspaceId())->where('projects.public_id', $projectId)
            ->where('issues.'.$column, $this->query('issues')->where('public_id', $issueId)->select('id'));
    }

    /** 僅判斷指定父項是否有子任務／Epic所屬卡片，不載入原始內容。
     * @param  string  $projectId  專案公開ID
     * @param  string  $issueId  父項公開ID
     * @param  bool  $epic  是否檢查Epic所屬卡片
     * @param  bool  $unclosed  是否只查未結案卡片
     * @return bool
     */
    public function issueHasChildren(string $projectId, string $issueId, bool $epic = false, bool $unclosed = false): bool
    {
        $query = $this->issueChildrenQuery($projectId, $issueId, $epic ? 'epic_id' : 'parent_issue_id');
        if ($unclosed) {
            $query->join('project_statuses', 'project_statuses.id', '=', 'issues.state_id')->where('project_statuses.code', '!=', 'closed');
        }

        return $query->exists();
    }

    /** 型別變更只檢查該卡有效工時是否存在。
     * @param  string  $projectId  專案公開ID
     * @param  string  $issueId  任務公開ID
     * @return bool
     */
    public function issueHasActiveTime(string $projectId, string $issueId): bool
    {
        return DB::table('time_entries')->join('issues', 'issues.id', '=', 'time_entries.issue_id')
            ->join('projects', 'projects.id', '=', 'issues.project_id')
            ->where('time_entries.workspace_id', $this->workspaceId())->where('projects.public_id', $projectId)
            ->where('issues.public_id', $issueId)->whereNull('time_entries.voided_at')->exists();
    }

    /** 同租戶／專案查Issue附件存在性, 不載內容
     * @param  string  $projectId  專案公開ID
     * @param  string  $issueId  Issue公開ID
     * @return bool 是否有附件metadata
     */
    public function issueHasAttachments(string $projectId, string $issueId): bool
    {
        if (! Schema::hasTable('attachments')) {
            return false;
        }

        return DB::table('attachments')->join('issues as attached_issue', 'attached_issue.id', '=', 'attachments.issue_id')
            ->join('projects as attached_project', 'attached_project.id', '=', 'attachments.project_id')
            ->where('attachments.workspace_id', $this->workspaceId())->whereColumn('attached_issue.workspace_id', 'attachments.workspace_id')
            ->whereColumn('attached_issue.project_id', 'attachments.project_id')->where('attached_issue.public_id', $issueId)
            ->where('attached_project.public_id', $projectId)->exists();
    }

    /** 刪除前檢查：任何工時（含已作廢）都保留稽核價值，存在時不可刪卡。
     * @param  string  $projectId  專案公開ID
     * @param  string  $issueId  任務公開ID
     * @return bool 是否有任何工時紀錄
     */
    public function issueHasAnyTime(string $projectId, string $issueId): bool
    {
        return DB::table('time_entries')->join('issues', 'issues.id', '=', 'time_entries.issue_id')
            ->join('projects', 'projects.id', '=', 'issues.project_id')
            ->where('time_entries.workspace_id', $this->workspaceId())->where('projects.public_id', $projectId)
            ->where('issues.public_id', $issueId)->exists();
    }

    /**
     * TT-250：永久刪除單張卡片及其附屬列，並在Project留下刪除墓碑事件；交易、授權與關聯端revision由呼叫者負責。
     * 留言、外部識別隨外鍵cascade；通知預覽、卡片歷史、殘留關聯與看板收錄列刪除；轉換來源待辦保留但解除卡片連結。
     *
     * @param  string  $issueId  已授權任務公開ID
     * @param  array{actor:string,at:string,identifier:string,title:string,issueId:string}  $tombstone  刪除事實，不含描述或留言內容
     * @return void
     */
    public function deleteIssue(string $issueId, array $tombstone): void
    {
        $workspaceId = $this->workspaceId();
        $row = DB::table('issues')->where('workspace_id', $workspaceId)->where('public_id', $issueId)->first(['id', 'project_id'])
            ?? throw new \RuntimeException('任務不存在');
        DB::table('issue_notifications')->where('workspace_id', $workspaceId)->where('issue_id', $row->id)->delete();
        DB::table('todos')->where('workspace_id', $workspaceId)->where('converted_issue_id', $row->id)->update(['converted_issue_id' => null]);
        DB::table('audit_events')->where('workspace_id', $workspaceId)->where('entity_type', 'issues')->where('entity_id', $row->id)->delete();
        DB::table('issue_relations')->where('workspace_id', $workspaceId)
            ->where(fn ($q) => $q->where('issue_id', $row->id)->orWhere('related_issue_id', $row->id))->delete();
        DB::table('board_issues')->where('workspace_id', $workspaceId)->where('issue_id', $row->id)->delete();
        $stream = ['workspace_id' => $workspaceId, 'entity_type' => 'projects', 'entity_id' => $row->project_id, 'stream' => 'issueDeletions'];
        DB::table('audit_events')->insert([...$stream, 'position' => (int) (DB::table('audit_events')->where($stream)->max('position') ?? -1) + 1,
            'event_data' => json_encode($tombstone, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        DB::table('issues')->where('workspace_id', $workspaceId)->where('id', $row->id)->delete();
        unset($this->identities['issues'][$row->id]);
    }

    /** 以固定100筆批次與穩定主鍵順序逐一提供必須同步Epic的子項ID。
     * @param  string  $projectId  專案公開ID
     * @param  string  $issueId  父項公開ID
     * @return \Generator<int, string>
     */
    public function issueChildIds(string $projectId, string $issueId): \Generator
    {
        foreach ($this->issueChildrenQuery($projectId, $issueId, 'parent_issue_id')->select(['issues.id', 'issues.public_id'])->lazyById(100, 'issues.id', 'id') as $row) {
            yield $row->public_id;
        }
    }

    /**
     * 讀取本人當日有效分鐘，不取得其他作者／日期的原始紀錄。
     *
     * @param  string  $userId  填報者User ID
     * @param  string  $date  工作日（Asia/Taipei）
     * @param  string  $excludeId  修改時排除原公開ID
     * @return int 當日有效工時分鐘數
     */
    public function dailyMinutes(string $userId, string $date, string $excludeId = ''): int
    {
        return (int) DB::table('time_entries')->join('workspace_members', 'workspace_members.id', '=', 'time_entries.member_id')
            ->where('time_entries.workspace_id', $this->workspaceId())
            ->whereColumn('workspace_members.workspace_id', 'time_entries.workspace_id')
            ->where('workspace_members.user_id', $userId)->where('time_entries.work_date', $date)
            ->whereNull('time_entries.voided_at')
            ->when($excludeId !== '', fn ($query) => $query->where('time_entries.public_id', '!=', $excludeId))
            ->sum('time_entries.minutes');
    }

    /** 批次解析實際使用的外鍵，每批最多100；只暫存正向不可變ID，不保存資格或Model。
     *
     * @param  string  $kind  實體種類
     * @param  array<int|string>  $ids  指定內部主鍵或公開ID
     * @param  bool  $internal  是否以內部主鍵查詢
     * @return void
     */
    private function primeIdentities(string $kind, array $ids, bool $internal): void
    {
        $known = $this->identities[$kind] ?? [];
        $missing = array_values(array_unique(array_filter($ids, fn ($id) => $id !== null && $id !== ''
            && ($internal ? ! isset($known[$id]) : ! in_array($id, $known, true)))));
        foreach (array_chunk($missing, 100) as $batch) {
            $rows = $this->query($kind)->whereIn($internal ? 'id' : $this->publicColumn($kind), $batch)
                ->pluck($this->publicColumn($kind), 'id')->all();
            $this->identities[$kind] = ($this->identities[$kind] ?? []) + $rows;
        }
    }

    /** 每次讀取以當次主列預載所需對照及成員欄位，不掃全部Workspace參照。
     *
     * @param  string  $kind  實體種類
     * @param  array<object>  $rows  當次實際讀取的主列
     * @return void
     */
    private function prepareRead(string $kind, array $rows): void
    {
        $this->children->prepare($kind, array_map(fn ($row) => $row->id, $rows));
        $references = ['issues' => $this->children->issueReferenceIds($kind)];
        $stateIds = [];
        foreach ($rows as $row) {
            $this->identities[$kind][$row->id] = $row->{$this->publicColumn($kind)};
            foreach ($this->fields($kind) as [$field, $column, $type, $default, $reference]) {
                if ($type === 'ref') {
                    $references[$reference][] = $row->{$column};
                } elseif ($type === 'state') {
                    $stateIds[] = $row->{$column};
                }
            }
        }
        foreach ($references as $reference => $ids) {
            $this->primeIdentities($reference, $ids, true);
        }
        foreach (array_chunk(array_values(array_unique($stateIds)), 100) as $ids) {
            $this->states += DB::table('project_statuses')->where('workspace_id', $this->workspaceId())->whereIn('id', $ids)->pluck('code', 'id')->all();
        }
        if ($kind === 'members') {
            foreach (array_chunk(array_map(fn ($row) => $row->user_id, $rows), 100) as $ids) {
                $this->users += User::whereIn('id', $ids)->get(['id', 'name', 'email'])->keyBy('id')->all();
            }
        }
    }

    /** 批次讀指定子集合參照，回傳ID對照，不增加內容或資格快取。
     *
     * @param  string  $kind  實體種類
     * @param  array<int>  $ids  明確參照的內部主鍵
     * @return array<int, string>
     */
    public function publicIds(string $kind, array $ids): array
    {
        $this->primeIdentities($kind, $ids, true);
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = $this->publicId($kind, $id);
        }

        return $result;
    }

    /**
     * 歷史關聯表先於Markdown欄位建立，尚未升級時保留原稿於遷移相容資料。
     *
     * @param  string  $kind  實體種類
     * @return array 目前schema可使用的欄位映射
     */
    private function fields(string $kind): array
    {
        $fields = EntityDefinitions::ENTITIES[$kind]['fields'];
        if ($kind !== 'todos') {
            return $fields;
        }
        $this->hasTodoContent ??= Schema::hasColumn('todos', 'content');

        return $this->hasTodoContent ? $fields : array_values(array_filter($fields, fn ($field) => $field[1] !== 'content'));
    }

    /** 由內部外鍵取得公開ID。
     * @param  string  $kind  實體種類
     * @param  ?int  $id  資料庫主鍵
     * @return string
     */
    public function publicId(string $kind, ?int $id): string
    {
        if ($id === null) {
            return '';
        }

        $this->primeIdentities($kind, [$id], true);

        return (string) ($this->identities[$kind][$id] ?? throw new \RuntimeException('關聯資料不存在'));
    }

    /** 由公開ID查詢同租戶內部主鍵。
     * @param  string  $kind  目標種類
     * @param  string  $id  公開ID
     * @return int
     */
    public function internalId(string $kind, string $id): int
    {
        $this->primeIdentities($kind, [$id], false);
        $key = array_search($id, $this->identities[$kind] ?? [], true);
        if ($key === false) {
            DomainError::fail('INVALID_INPUT', '關聯資料不存在或不屬於目前工作空間');
        }

        return (int) $key;
    }

    /** 把資料庫欄位及關聯還原為既有API格式。
     * @param  string  $kind  實體種類
     * @param  object  $row  資料模型
     * @return array
     */
    private function serialize(string $kind, object $row): array
    {
        $value = ['id' => $row->{$this->publicColumn($kind)}];
        if ($kind === 'members') {
            $user = $this->users[$row->user_id];
            $value += [
                'name' => $user->name,
                'email' => $user->email,
            ];
        }
        foreach ($this->fields($kind) as [$field, $column, $type, $default, $reference]) {
            $raw = $row->{$column};
            if ($type === 'optionalText' && $raw === null) {
                continue;
            }
            if ($type === 'ref') {
                if ($raw === null && in_array($field, ['convertedIssueId', 'convertedProjectId'], true)) {
                    continue;
                }
                $value[$field] = $this->publicId($reference, $raw);
            } elseif ($type === 'workspace_ref') {
                $value[$field] = $this->publicId('workspaces', $row->workspace_id);
            } elseif ($type === 'state') {
                $value[$field] = $this->states[$raw];
            } elseif ($type === 'time' && $raw === null && ! in_array($field, ['completedAt', 'returnedAt', 'removedAt', 'readAt', 'consumedAt'], true)) {
                continue;
            } elseif ($type === 'time') {
                $value[$field] = DatabaseTimes::public($raw);
            } elseif ($type === 'date' && $raw === null) {
                $value[$field] = '';
            } else {
                $value[$field] = $type === 'int' ? (int) $raw : $raw;
            }
        }
        if ($kind === 'projectAcls') {
            unset($value['projectId']);
        }
        $value += $this->children->read($kind, $row, $this);
        $value += $row->legacy_attributes ?? [];
        // JSON object順序不是契約；固定常用DTO順序方便人工比較與舊測試。
        $order = match ($kind) {
            'projects' => ['id', 'name', 'code', 'leadId', 'description', 'color', 'nextNumber', 'revision', 'statuses', 'settingsHistory', 'createdAt', 'updatedAt', 'detailsHistory'],
            'issues' => ['id', 'projectId', 'identifier', 'title', 'description', 'state', 'priority', 'type', 'assignee', 'dueDate', 'epicId', 'estimateMinutes', 'parentIssueId', 'relatedIssueIds', 'revision', 'createdAt', 'updatedAt', 'comments', 'history'],
            'timeEntries' => ['id', 'issueId', 'projectId', 'userId', 'createdAt', 'history', 'workDate', 'minutes', 'note', 'revision', 'updatedAt', 'voidedAt'],
            'boards' => ['id', 'projectId', 'name', 'selectedIssueIds', 'isDefault', 'revision', 'createdBy', 'createdAt', 'updatedAt', 'archivedAt'],
            default => array_keys($value),
        };

        return array_replace(array_intersect_key(array_fill_keys($order, null), $value), $value);
    }

    /** 在同交易保存主表與子表，交由外鍵拒絕跨租戶關聯。
     * @param  string  $kind  實體種類
     * @param  string  $id  公開ID
     * @param  array  $value  領域服務已驗證的欄位
     * @return void
     */
    public function put(string $kind, string $id, array $value): void
    {
        try {
            DB::transaction(function () use ($kind, $id, $value) {
                $this->identities = [];
                $this->users = [];
                $definition = EntityDefinitions::ENTITIES[$kind];
                $attributes = [];
                if ($kind !== 'workspaces') {
                    $workspace = $definition['scope'] === 'global' ? $value['workspaceId'] ?? $id : $this->workspacePublicId;
                    $attributes['workspace_id'] = ($workspace === $this->workspacePublicId ? $this->workspaceId() : Workspace::where('public_id', $workspace)->value('id'))
                        ?? throw new \RuntimeException('工作空間不存在');
                }
                if ($kind === 'projectAcls') {
                    $value['projectId'] = $id;
                }
                $handled = ['id', ...match ($kind) {
                    'projects' => ['statuses', 'settingsHistory', 'detailsHistory'],
                    'issues' => ['comments', 'relatedIssueIds', 'history'],
                    'boards' => ['selectedIssueIds', 'isDefault'],
                    'timeEntries', 'todos', 'projectAcls' => ['history'],
                    default => [],
                }];
                if ($kind === 'members') {
                    $handled = [...$handled, 'name', 'email'];
                }
                $references = [];
                foreach ($this->fields($kind) as [$field, $column, $type, $default, $reference]) {
                    if ($type === 'ref') {
                        $references[$reference][] = $value[$field] ?? $default;
                    }
                }
                if (in_array($kind, ['issues', 'boards'], true)) {
                    $references['issues'] = [...($references['issues'] ?? []), ...($value[$kind === 'issues' ? 'relatedIssueIds' : 'selectedIssueIds'] ?? [])];
                }
                foreach ($references as $reference => $ids) {
                    $this->primeIdentities($reference, $ids, false);
                }
                foreach ($this->fields($kind) as [$field, $column, $type, $default, $reference]) {
                    $handled[] = $field;
                    $raw = $value[$field] ?? $default;
                    if ($type === 'ref') {
                        $attributes[$column] = $raw === '' || $raw === null ? null : $this->internalId($reference, $raw);
                    } elseif ($type === 'state') {
                        $attributes[$column] = DB::table('project_statuses')->where('project_id', $attributes['project_id'])->where('code', $raw)->value('id') ?? throw new \RuntimeException('任務狀態不存在');
                    } elseif ($type === 'time') {
                        $attributes[$column] = DatabaseTimes::store($raw === '' ? null : $raw);
                    } elseif ($type === 'workspace_ref') {
                        // 全球邀請的Workspace外鍵已由受信任ID設定。
                        continue;
                    } else {
                        $attributes[$column] = $raw === '' && in_array($type, ['time', 'date'], true) ? null : $raw;
                    }
                }
                if ($kind === 'issues' && ! $this->query($kind)->where('public_id', $id)->exists()) {
                    $attributes['storage_position'] = (int) $this->query($kind)->max('storage_position') + 1;
                }
                $attributes['legacy_attributes'] = array_diff_key($value, array_flip($handled));
                $row = $this->query($kind)->updateOrCreate([$this->publicColumn($kind) => $id], $attributes);
                $this->identities[$kind][$row->id] = $row->{$this->publicColumn($kind)};
                $this->children->write($kind, $row, $value, $this);
            });
        } finally {
            $this->identities = [];
            $this->users = [];
            $this->states = [];
        }
    }
}
