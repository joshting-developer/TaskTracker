<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Data\TimeReportData;
use App\Domain\DomainError;
use App\Models\TimeEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** SQL聚合與分頁有效工時；只接收Service授權的工作空間及專案，不判斷角色。 */
class TimeReportRepository
{
    private const PER_PAGE = 20;

    /**
     * 在SQL限定可分析的專案, 角色清單由Service依有效permission映射
     *
     * @param  string  $workspaceId  可信工作區公開ID
     * @param  string  $userId  可信操作者
     * @param  bool  $readAny  所有必要permission皆具有Any能力
     * @param  list<string>  $roles  同時具備必要permission的Project角色
     * @return Builder 只投影專案公開ID的授權子查詢
     */
    public function authorizedProjects(string $workspaceId, string $userId, bool $readAny, array $roles): Builder
    {
        return DB::table('projects')->join('workspaces as scope_workspace', 'scope_workspace.id', '=', 'projects.workspace_id')
            ->where('scope_workspace.public_id', $workspaceId)
            ->when(! $readAny, fn ($q) => $q->whereExists(fn ($q) => $q->selectRaw('1')->from('project_members as assignment')
                ->join('workspace_members as actor', 'actor.id', '=', 'assignment.member_id')
                ->whereColumn('assignment.workspace_id', 'projects.workspace_id')->whereColumn('assignment.project_id', 'projects.id')
                ->whereColumn('actor.workspace_id', 'projects.workspace_id')->where('actor.user_id', $userId)->whereNull('actor.disabled_at')
                ->whereNull('assignment.removed_at')->whereIn('assignment.role_id', $roles)))
            ->select('projects.public_id');
    }

    /**
     * 驗證指定專案位於授權查詢內
     *
     * @param  Builder  $projects  已授權專案子查詢
     * @param  string  $projectId  待查公開ID
     * @return bool 是否存在
     */
    public function projectExists(Builder $projects, string $projectId): bool
    {
        return (clone $projects)->where('projects.public_id', $projectId)->exists();
    }

    /**
     * 驗證指定成員存在於當前工作區, 保留停用成員的歷史分析
     *
     * @param  string  $workspaceId  可信工作區公開ID
     * @param  string  $userId  待查成員User ID
     * @return bool 同租戶成員是否存在
     */
    public function memberExists(string $workspaceId, string $userId): bool
    {
        return DB::table('workspace_members as member')->join('workspaces as w', 'w.id', '=', 'member.workspace_id')
            ->where('w.public_id', $workspaceId)->where('member.user_id', $userId)->exists();
    }

    /**
     * 只讀有界專案或成員選項, 不讀描述、狀態或歷史
     *
     * @param  string  $workspaceId  可信工作區公開ID
     * @param  Builder  $projects  已授權專案ID子查詢
     * @param  string  $kind  projects或members
     * @param  PageQueryData  $page  有界搜尋／分頁
     * @return LengthAwarePaginator 最小id/name投影
     */
    public function options(string $workspaceId, Builder $projects, string $kind, PageQueryData $page): LengthAwarePaginator
    {
        if ($kind === 'projects') {
            $query = DB::table('projects')->whereIn('public_id', clone $projects)->whereIn('workspace_id', DB::table('workspaces')->select('id')->where('public_id', $workspaceId));
            $id = 'projects.public_id';
            $name = 'projects.name';
        } else {
            $query = DB::table('workspace_members as member')->join('workspaces as w', 'w.id', '=', 'member.workspace_id')->join('users', 'users.id', '=', 'member.user_id')->where('w.public_id', $workspaceId);
            $id = 'users.id';
            $name = 'users.name';
        }
        if ($page->search !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($page->search)).'%';
            $query->whereRaw("LOWER($name) LIKE ? ESCAPE '!'", [$term]);
        }

        return $query->orderBy($name)->orderBy($id)->paginate($page->perPage, ["$id as id", "$name as name"], 'page', $page->page);
    }

    /**
     * 依成員／專案配對與工作日分別聚合，不將原始工時載入PHP。
     *
     * @param  string  $workspaceId  可信工作空間公開ID
     * @param  list<string>|Builder  $projectIds  已授權專案公開ID
     * @param  TimeReportData  $filters  已驗證日期及篩選
     * @param  bool  $includeAllocations  明細不需重算成員專案配對
     * @return array{allocations:array,days:array} 僅含識別碼、日期及整數統計
     */
    public function summary(string $workspaceId, array|Builder $projectIds, TimeReportData $filters, bool $includeAllocations = true): array
    {
        $base = $this->query($workspaceId, $projectIds, $filters);
        $allocations = $includeAllocations ? (clone $base)->join('users', 'users.id', '=', 'workspace_members.user_id')->select('workspace_members.user_id', 'projects.public_id as project_id', 'projects.name as project_name', 'projects.code as project_code', 'users.name as member_name')
            ->selectRaw('SUM(time_entries.minutes) as minutes, COUNT(*) as entry_count, MIN(time_entries.id) as first_id')
            ->groupBy('workspace_members.user_id', 'projects.public_id', 'projects.name', 'projects.code', 'users.name')->orderBy('first_id')->limit(2001)->get() : collect();
        if ($allocations->count() > 2000) {
            DomainError::fail('INVALID_INPUT', '分析配對超過2000組, 請縮小專案、成員或日期範圍');
        }
        $days = (clone $base)->select('time_entries.work_date')
            ->selectRaw('SUM(time_entries.minutes) as minutes, COUNT(*) as entry_count')
            ->groupBy('time_entries.work_date')->orderBy('time_entries.work_date')->get();

        return [
            'allocations' => $allocations->map(fn ($row) => ['userId' => $row->user_id, 'projectId' => $row->project_id, 'projectName' => $row->project_name, 'projectCode' => $row->project_code, 'memberName' => $row->member_name, 'minutes' => (int) $row->minutes, 'entryCount' => (int) $row->entry_count])->all(),
            'days' => $days->map(fn ($row) => ['date' => $row->work_date, 'minutes' => (int) $row->minutes, 'entryCount' => (int) $row->entry_count])->all(),
        ];
    }

    /**
     * 單人成員群組與指定群組的原始紀錄，各在SQL排序及分頁20筆。
     *
     * @param  string  $workspaceId  可信工作空間公開ID
     * @param  list<string>|Builder  $projectIds  已授權且可讀任務的專案公開ID
     * @param  TimeReportData  $filters  已驗證的單人成員明細條件
     * @param  array<string,array>  $days  已聚合的每日總分鐘索引
     * @return array 與既有明細API相同的rows／分頁／expanded結構
     */
    public function details(string $workspaceId, array|Builder $projectIds, TimeReportData $filters, array $days): array
    {
        $base = $this->query($workspaceId, $projectIds, $filters);
        $columns = ['issues.public_id', 'issues.identifier', 'issues.title', 'projects.public_id as project_id', 'projects.name as project_name'];
        $groups = ['issues.public_id', 'issues.identifier', 'issues.title', 'projects.public_id', 'projects.name'];
        if ($filters->detailGroup === 'dates') {
            $columns[] = 'time_entries.work_date';
            $groups[] = 'time_entries.work_date';
        }
        $grouped = (clone $base)->select($columns)
            ->selectRaw('SUM(time_entries.minutes) as minutes, COUNT(*) as entry_count')->groupBy($groups);
        if ($filters->detailGroup === 'dates') {
            $grouped->orderByDesc('time_entries.work_date')->orderBy('issues.identifier')->orderBy('issues.public_id');
        } else {
            $grouped->orderByDesc('minutes')->orderBy('issues.public_id');
        }
        $page = $this->page($grouped, $filters->detailPage);
        $page['rows'] = array_map(function ($row) use ($filters, $days) {
            $date = $filters->detailGroup === 'dates' ? $row->work_date : null;

            return ['key' => $date === null ? $row->public_id : $date.'|'.$row->public_id,
                'date' => $date, 'issueId' => $row->public_id, 'identifier' => $row->identifier,
                'title' => $row->title, 'projectId' => $row->project_id, 'projectName' => $row->project_name,
                'minutes' => (int) $row->minutes, 'entryCount' => (int) $row->entry_count,
                'dayMinutes' => $date === null ? null : $days[$date]['minutes']];
        }, $page['rows']);
        $page['groupBy'] = $filters->detailGroup;
        $page['expanded'] = null;
        if ($filters->entryGroupKey !== '') {
            $key = $filters->entryGroupKey;
            if ($filters->detailGroup === 'dates') {
                $parts = explode('|', $key, 2);
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $parts[0]);
                if (count($parts) !== 2 || ! $date || $date->format('Y-m-d') !== $parts[0]) {
                    DomainError::fail('NOT_FOUND', '找不到可查看的工時群組');
                }
                $base->where('time_entries.work_date', $parts[0]);
                $key = $parts[1];
            }
            $entries = $base->where('issues.public_id', $key)
                ->select(['time_entries.public_id as id', 'time_entries.work_date', 'time_entries.minutes', 'time_entries.note'])
                ->orderByDesc('time_entries.work_date')->orderBy('time_entries.public_id');
            $expanded = $this->page($entries, $filters->entryPage);
            if ($expanded['total'] === 0) {
                DomainError::fail('NOT_FOUND', '找不到可查看的工時群組');
            }
            $expanded['rows'] = array_map(fn ($row) => ['id' => $row->id, 'workDate' => $row->work_date, 'minutes' => (int) $row->minutes, 'note' => $row->note], $expanded['rows']);
            $page['expanded'] = ['key' => $filters->entryGroupKey, ...$expanded];
        }

        return $page;
    }

    /**
     * 以相同WHERE作所有聚合／明細查詢，不載入Model／關聯歷史。
     *
     * @param  string  $workspaceId  可信租戶公開ID
     * @param  list<string>|Builder  $projectIds  已授權專案公開ID
     * @param  TimeReportData  $filters  日期／專案／作者交集
     * @return Builder 尚未執行的限定查詢
     */
    private function query(string $workspaceId, array|Builder $projectIds, TimeReportData $filters): Builder
    {
        return TimeEntry::query()->toBase()
            ->join('workspaces', 'workspaces.id', '=', 'time_entries.workspace_id')
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->join('workspace_members', 'workspace_members.id', '=', 'time_entries.member_id')
            ->join('issues', 'issues.id', '=', 'time_entries.issue_id')
            ->where('workspaces.public_id', $workspaceId)
            ->whereColumn('projects.workspace_id', 'time_entries.workspace_id')
            ->whereColumn('workspace_members.workspace_id', 'time_entries.workspace_id')
            ->whereColumn('issues.workspace_id', 'time_entries.workspace_id')
            ->whereColumn('issues.project_id', 'time_entries.project_id')
            ->whereIn('projects.public_id', $projectIds)
            ->whereNull('time_entries.voided_at')
            ->whereBetween('time_entries.work_date', [$filters->startDate, $filters->endDate])
            ->when($filters->projectId !== '', fn ($query) => $query->where('projects.public_id', $filters->projectId))
            ->when($filters->userId !== '', fn ($query) => $query->where('workspace_members.user_id', $filters->userId));
    }

    /**
     * 計算群組總數並收斂頁碼，在SQL執行LIMIT／OFFSET。
     *
     * @param  Builder  $query  已投影及穩定排序的資料／群組查詢
     * @param  int  $page  已驗證的要求頁碼
     * @return array{rows:array,page:int,perPage:int,total:int,lastPage:int} 最多20筆
     */
    private function page(Builder $query, int $page): array
    {
        $total = DB::query()->fromSub((clone $query)->reorder(), 'report_rows')->count();
        $last = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $last);

        return ['rows' => $query->forPage($page, self::PER_PAGE)->get()->all(), 'page' => $page,
            'perPage' => self::PER_PAGE, 'total' => $total, 'lastPage' => $last];
    }
}
