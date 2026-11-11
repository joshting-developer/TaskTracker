<?php

namespace App\Repositories;

use App\Data\IssueQueryData;
use App\Data\PageQueryData;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** 只讀目前已授權Project的任務；所有搜尋／排序／分頁均在SQL執行。 */
class IssueReadRepository
{
    /** 以可信Project的內部ID及工作區限定資料來源。 */
    private function query(Project $project): Builder
    {
        return Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id);
    }

    /** 只預載本投影需要的識別欄位。 */
    private function relations(): array
    {
        return ['project:id,public_id', 'status:id,code', 'assignee:id,user_id', 'epic:id,public_id', 'parent:id,public_id'];
    }

    /** 明確區分列表與詳細欄位，避免讀入無關內容。 */
    private function columns(bool $detail = false): array
    {
        return ['id', 'workspace_id', 'public_id', 'project_id', 'identifier', 'title', 'type', 'state_id', 'priority', 'assignee_id', 'epic_id', 'parent_issue_id', 'due_date', 'estimate_minutes', 'revision', 'created_at', 'updated_at', ...($detail ? ['description'] : [])];
    }

    /** 於資料庫執行條件與分頁，限制回傳資料量。 */
    public function page(Project $project, string $userId, IssueQueryData $filters): LengthAwarePaginator
    {
        $q = $this->query($project)->with($this->relations());
        if ($filters->identifier !== '') {
            $q->where('identifier', $filters->identifier);
        }
        if ($filters->states !== []) {
            $q->whereHas('status', fn ($q) => $q->whereIn('code', $filters->states));
        }
        foreach (['priority', 'type'] as $field) {
            if ($filters->{$field} !== '') {
                $q->where($field, $filters->{$field});
            }
        }
        if ($filters->scope === 'mine') {
            $q->whereHas('assignee', fn ($q) => $q->where('user_id', $userId));
        }
        if ($filters->assignee !== '') {
            $q->whereHas('assignee', fn ($q) => $q->where('user_id', $filters->assignee));
        }
        if ($filters->epicId === 'unlinked') {
            $q->whereNull('epic_id')->where('type', '!=', 'epic');
        } elseif ($filters->epicId !== '') {
            $q->where(fn ($q) => $q->where('public_id', $filters->epicId)->orWhereHas('epic', fn ($q) => $q->where('public_id', $filters->epicId)));
        }
        if ($filters->condition === 'overdue') {
            $q->whereHas('status', fn ($q) => $q->where('code', '!=', 'closed'))->where('due_date', '<', now('Asia/Taipei')->toDateString());
        } elseif ($filters->condition === 'unassigned') {
            $q->whereNull('assignee_id');
        }
        if ($filters->pagination->search !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->pagination->search)).'%';
            $q->where(function ($q) use ($term) {
                $q->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$term])->orWhereRaw("LOWER(identifier) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereHas('assignee.user', fn ($q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]));
            });
        }
        if ($filters->sort === 'priority') {
            $q->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END");
        } elseif ($filters->sort === 'due') {
            $q->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')->orderBy('due_date');
        }

        return $q->orderByDesc('updated_at')->orderBy('identifier')->orderBy('public_id')
            ->paginate($filters->pagination->perPage, $this->columns(), 'page', $filters->pagination->page);
    }

    /** 讀取同工作區指定專案及此頁需要的關聯。 */
    public function find(Project $project, string $id): ?Issue
    {
        return $this->query($project)->where('public_id', $id)->with($this->relations())->first($this->columns(true));
    }

    /** 活動入口只需父列識別，不讀描述與其他關聯。 */
    public function reference(Project $project, string $id): ?Issue
    {
        return $this->query($project)->where('public_id', $id)->first(['id', 'workspace_id', 'project_id', 'public_id']);
    }

    /** 活動分頁有固定父任務條件；不將其他任務內容載入記憶體。 */
    public function comments(Issue $issue, PageQueryData $filters): LengthAwarePaginator
    {
        return DB::table('issue_comments')->where('issue_id', $issue->id)->orderBy('position')->orderBy('id')
            ->paginate($filters->perPage, ['public_id as id', 'content', 'author', 'created_at'], 'page', $filters->page);
    }

    /** 只查這個任務的異動分頁，維持穩定排序。 */
    public function history(Issue $issue, PageQueryData $filters): LengthAwarePaginator
    {
        return DB::table('audit_events')->where('workspace_id', $issue->workspace_id)->where('entity_type', 'issues')
            ->where('entity_id', $issue->id)->where('stream', 'history')->orderByDesc('position')->orderByDesc('id')
            ->paginate($filters->perPage, ['event_data'], 'page', $filters->page);
    }
}
