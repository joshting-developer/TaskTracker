<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/** 專案讀取投影；呼叫者提供已驗證工作區與有效公開ID集合。 */
class ProjectReadRepository
{
    /**
     * 已授權Project只讀狀態欄位；多取一筆偵測歷史超限，不靜默截斷。
     *
     * @param  Project  $project  已通過權限檢查的Project參照
     * @return Collection<int,ProjectStatus> 至多21筆狀態，不查描述／成員／卡片
     */
    public function statuses(Project $project): Collection
    {
        return ProjectStatus::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)
            ->orderBy('position')->orderBy('id')->limit(21)->get(['code', 'label', 'color']);
    }

    /**
     * 只供當次授權組合使用，不載入專案內容或歷史。
     *
     * @param  string  $workspaceId  Workspace內部ID
     * @param  array  $ids  Project公開ID
     * @return Collection<int, Project>
     */
    public function references(string $workspaceId, array $ids): Collection
    {
        if (count($ids) > 100) {
            throw new \LogicException('Project reference batches are limited to 100');
        }

        return Project::whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))
            ->whereIn('public_id', $ids)->limit(count($ids))->get(['id', 'workspace_id', 'public_id']);
    }

    /**
     * 單人讀取資格只查本人的Project指派，不載入整個工作區成員配置。
     *
     * @param  string  $workspaceId  Workspace內部ID
     * @param  string  $userId  操作者User ID
     * @param  array  $projectIds  Project內部ID
     * @return Collection<int, ProjectMember>
     */
    public function assignments(string $workspaceId, string $userId, array $projectIds): Collection
    {
        return ProjectMember::whereHas('project.workspace', fn ($q) => $q->where('public_id', $workspaceId))
            ->whereHas('project', fn ($q) => $q->whereIn('public_id', $projectIds))
            ->whereHas('member', fn ($q) => $q->where('user_id', $userId))
            ->with(['project:id,public_id', 'member:id,user_id'])
            ->limit(count($projectIds))->get(['id', 'public_id', 'project_id', 'member_id', 'role_id', 'removed_at']);
    }

    /**
     * 由SQL執行搜尋與分頁，不讓未授權Project進入回傳集合。
     *
     * @param  string  $workspaceId  Workspace內部ID
     * @param  string  $userId  操作者User ID
     * @param  bool  $readAny  是否可讀全部Project
     * @param  array  $readRoleIds  具Project讀取權限的角色
     * @param  PageQueryData  $filters  分頁與搜尋
     * @return LengthAwarePaginator Project分頁
     */
    public function page(string $workspaceId, string $userId, bool $readAny, array $readRoleIds, PageQueryData $filters): LengthAwarePaginator
    {
        return Project::whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))
            ->when(! $readAny, fn ($q) => $q->whereHas('members', fn ($q) => $q
                ->whereNull('removed_at')->whereIn('role_id', $readRoleIds)
                ->whereHas('member', fn ($q) => $q->where('user_id', $userId)->whereNull('disabled_at'))))
            ->with(['lead:id,user_id', 'lead.user:id,name'])
            ->when($filters->search !== '', fn ($q) => $q->where(function ($q) use ($filters) {
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->search)).'%';
                $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])->orWhereRaw("LOWER(code) LIKE ? ESCAPE '!'", [$term]);
            }))->orderByDesc('updated_at')->orderBy('public_id')
            ->paginate($filters->perPage, ['id', 'workspace_id', 'public_id', 'name', 'code', 'description', 'color', 'lead_id', 'revision', 'created_at', 'updated_at'], 'page', $filters->page);
    }

    /**
     * 只查可寫卡片的Project選項，不載描述、成員或狀態。
     *
     * @param  string  $workspaceId  可信Workspace公開ID
     * @param  string  $userId  可信操作者ID
     * @param  bool  $writeAny  是否具有issue.writeAny
     * @param  array<int,string>  $writeRoles  由permission映射取得的角色ID
     * @param  PageQueryData  $filters  有界搜尋與分頁
     * @return LengthAwarePaginator<Project> 最小Project選項
     */
    public function options(string $workspaceId, string $userId, bool $writeAny, array $writeRoles, PageQueryData $filters): LengthAwarePaginator
    {
        $q = Project::whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId));
        if (! $writeAny) {
            $q->whereHas('members', fn ($q) => $q->whereNull('removed_at')->whereIn('role_id', $writeRoles)->whereHas('member', fn ($q) => $q->where('user_id', $userId)->whereNull('disabled_at')));
        }
        if ($filters->search !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->search)).'%';
            $q->where(fn ($q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])->orWhereRaw("LOWER(code) LIKE ? ESCAPE '!'", [$term]));
        }

        return $q->orderBy('name')->orderBy('public_id')->paginate($filters->perPage, ['id', 'public_id', 'name', 'code'], 'page', $filters->page);
    }

    /**
     * 讀取同工作區指定專案及此頁需要的關聯。
     *
     * @param  string  $workspaceId  Workspace內部ID
     * @param  string  $id  Project公開ID
     * @return Project|null 存在時的Project
     */
    public function find(string $workspaceId, string $id): ?Project
    {
        return Project::whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))
            ->where('public_id', $id)->with(['lead:id,user_id', 'lead.user:id,name', 'statuses:id,project_id,code,label,color,position'])
            ->first(['id', 'workspace_id', 'public_id', 'name', 'code', 'description', 'color', 'lead_id', 'revision', 'created_at', 'updated_at']);
    }

    /**
     * SQL依狀態聚合用量，不載入Issue內容或活動。
     *
     * @param  Project  $project  已授權專案
     * @return array<string,int> 狀態type對應卡片數量
     */
    public function statusUsage(Project $project): array
    {
        return Issue::where('issues.workspace_id', $project->workspace_id)->where('issues.project_id', $project->id)
            ->join('project_statuses as s', fn ($join) => $join->on('s.id', '=', 'issues.state_id')->on('s.project_id', '=', 'issues.project_id'))
            ->selectRaw('s.code, COUNT(*) as total')->groupBy('s.code')->pluck('total', 's.code')->map(fn ($count) => (int) $count)->all();
    }
}
