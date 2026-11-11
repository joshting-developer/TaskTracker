<?php

namespace App\Repositories;

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
        $this->identities = [];
        $this->users = [];
        $row = $this->query($kind)->where($this->publicColumn($kind), $id)->first();
        if (! $row) {
            return null;
        }
        $this->children->prepare($kind, [$row->id]);

        return $this->serialize($kind, $row);
    }

    /** 按插入順序讀取同一租戶資料並批次預載子表。
     * @param  string  $kind  實體種類
     * @return array
     */
    public function all(string $kind): array
    {
        $this->identities = [];
        $this->users = [];
        $query = $this->query($kind);
        if ($kind === 'issues') {
            $query->orderBy('storage_position');
        }
        $rows = $query->orderBy('id')->get();
        $this->children->prepare($kind, $rows->pluck('id')->all());

        return $rows->map(fn ($row) => $this->serialize($kind, $row))->all();
    }

    /** 批次取得同工作區任務所屬Project，不載入內容／留言／歷史；資格由Service判斷。
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

    /** 讀取本人當日有效分鐘，不取得其他作者／日期的原始紀錄。
     * @param  string  $excludeId  修改時排除原公開ID
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

    /** 批次建立不可變識別碼對照，避免每張卡查詢所有關聯。
     * @param  string  $kind  目標種類
     * @return array
     */
    private function identities(string $kind): array
    {
        if (! isset($this->identities[$kind])) {
            $this->identities[$kind] = $this->query($kind)->pluck($this->publicColumn($kind), 'id')->all();
        }

        return $this->identities[$kind];
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

        return (string) ($this->identities($kind)[$id] ?? throw new \RuntimeException('關聯資料不存在'));
    }

    /** 由公開ID查詢同租戶內部主鍵。
     * @param  string  $kind  目標種類
     * @param  string  $id  公開ID
     * @return int
     */
    public function internalId(string $kind, string $id): int
    {
        $key = array_search($id, $this->identities($kind), true);
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
            if (! $this->users) {
                $ids = $this->query('members')->pluck('user_id');
                $this->users = User::whereIn('id', $ids)->get()->keyBy('id')->all();
            }
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
                $value[$field] = Workspace::findOrFail($row->workspace_id)->public_id;
            } elseif ($type === 'state') {
                if (! isset($this->identities['states'])) {
                    $this->identities['states'] = DB::table('project_statuses')->where('workspace_id', $row->workspace_id)->pluck('code', 'id')->all();
                }
                $value[$field] = $this->identities['states'][$raw];
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
        DB::transaction(function () use ($kind, $id, $value) {
            $this->identities = [];
            $this->users = [];
            $definition = EntityDefinitions::ENTITIES[$kind];
            $attributes = [];
            if ($kind !== 'workspaces') {
                $workspace = $definition['scope'] === 'global' ? $value['workspaceId'] ?? $id : $this->workspacePublicId;
                $attributes['workspace_id'] = Workspace::where('public_id', $workspace)->value('id') ?? throw new \RuntimeException('工作空間不存在');
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
            $this->identities = [];
            $this->children->write($kind, $row, $value, $this);
        });
    }
}
