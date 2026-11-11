<?php

namespace App\Repositories;

use App\Data\IssueOptionQueryData;
use App\Data\IssueQueryData;
use App\Data\PageQueryData;
use App\Models\Issue;
use App\Models\Project;
use App\Queries\BoardIssueScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** 只讀目前已授權Project的任務；所有搜尋／排序／分頁均在SQL執行。 */
class IssueReadRepository
{
    /**
     * 注入只組合SQL收錄條件的協作者。
     *
     * @param  BoardIssueScope  $boardScope  看板收錄條件
     */
    public function __construct(private BoardIssueScope $boardScope) {}

    /**
     * 以已授權Project的內部鍵限定租戶及專案。
     *
     * @param  Project  $project  已驗證Project
     * @return Builder<Issue> 尚未執行的限定查詢
     */
    private function query(Project $project): Builder
    {
        return Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id);
    }

    /**
     * 定義摘要需要的識別與顯示關聯，禁止載入活動全集。
     *
     * @return array<int,string> 預載關聯與欄位
     */
    private function relations(): array
    {
        return ['project:id,public_id', 'status:id,code', 'assignee:id,user_id', 'assignee.user:id,name', 'epic:id,public_id,title,identifier,type,state_id', 'parent:id,public_id,title,identifier,type,state_id'];
    }

    /**
     * 加入已授權的工時SQL總計，不載入原始工時集合。
     *
     * @param  Builder  $q  已限定Project的任務查詢
     * @param  string|null  $timeUser  只可讀本人時的可信User ID
     * @param  bool  $teamTime  是否具團隊工時資格
     * @param  bool  $detail  是否需描述
     * @return void
     */
    private function timeProjection(Builder $q, ?string $timeUser, bool $teamTime, bool $detail = false): void
    {
        $q->select($this->columns($detail))->selectSub(DB::table('issues as epic_child')
            ->whereColumn('epic_child.workspace_id', 'issues.workspace_id')->whereColumn('epic_child.project_id', 'issues.project_id')
            ->whereColumn('epic_child.epic_id', 'issues.id')->selectRaw('COALESCE(SUM(estimate_minutes),0)'), 'epic_estimate_minutes');
        if ($timeUser !== null || $teamTime) {
            $minutes = DB::table('time_entries as tracked')->whereColumn('tracked.workspace_id', 'issues.workspace_id')
                ->whereColumn('tracked.project_id', 'issues.project_id')->whereNull('tracked.voided_at')
                ->where(function ($q) {
                    $q->whereColumn('tracked.issue_id', 'issues.id')->orWhere(function ($q) {
                        $q->where('issues.type', 'epic')->whereIn('tracked.issue_id', DB::table('issues as child')->select('child.id')->whereColumn('child.epic_id', 'issues.id'));
                    });
                });
            if (! $teamTime) {
                $minutes->whereIn('tracked.member_id', DB::table('workspace_members')->select('id')->where('user_id', $timeUser)->whereColumn('workspace_id', 'issues.workspace_id'));
            }
            $q->selectSub($minutes->selectRaw('COALESCE(SUM(minutes),0)'), 'spent_minutes');
        }
    }

    /**
     * 分開摘要與詳細欄位，只有詳細才讀描述。
     *
     * @param  bool  $detail  是否需要描述，預設false
     * @return array<int,string> 明確資料庫投影
     */
    private function columns(bool $detail = false): array
    {
        return ['id', 'workspace_id', 'public_id', 'project_id', 'identifier', 'title', 'type', 'state_id', 'priority', 'assignee_id', 'epic_id', 'parent_issue_id', 'due_date', 'estimate_minutes', 'revision', 'created_at', 'updated_at', ...($detail ? ['description'] : [])];
    }

    /**
     * 在SQL套用Project、看板、篩選及有界分頁，僅按資格聚合工時。
     *
     * @param  Project  $project  已授權Project
     * @param  string  $userId  可信操作者ID
     * @param  IssueQueryData  $filters  有界搜尋與排序
     * @param  ?int  $boardId  自訂看板內部ID，null不限制收錄
     * @param  ?string  $timeUser  僅讀本人工時的可信User ID
     * @param  bool  $teamTime  是否可讀團隊工時
     * @param  bool  $listOnly  是否只需要列表欄位，避免看板工時／估時聚合
     * @return LengthAwarePaginator<Issue> 任務摘要分頁
     */
    public function page(Project $project, string $userId, IssueQueryData $filters, ?int $boardId = null, ?string $timeUser = null, bool $teamTime = false, bool $listOnly = false): LengthAwarePaginator
    {
        $q = $this->query($project)->with($listOnly ? ['project:id,public_id', 'status:id,code', 'assignee:id,user_id', 'assignee.user:id,name'] : $this->relations());
        if ($boardId !== null) {
            $this->boardScope->apply($q, $boardId);
        }
        if ($listOnly) {
            $q->select(['id', 'workspace_id', 'public_id', 'project_id', 'identifier', 'title', 'type', 'state_id', 'priority', 'assignee_id', 'due_date', 'revision', 'created_at', 'updated_at']);
        } else {
            $this->timeProjection($q, $timeUser, $teamTime);
        }
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

    /**
     * 在SQL限制候選種類、Project、排除自己與文字搜尋，不讀描述或活動。
     *
     * @param  Project  $project  已授權Project
     * @param  IssueOptionQueryData  $filters  有界選項條件
     * @return LengthAwarePaginator<Issue> 選項分頁
     */
    public function options(Project $project, IssueOptionQueryData $filters): LengthAwarePaginator
    {
        $q = $this->query($project)->with(['status:id,code', 'epic:id,public_id', 'parent:id,public_id']);
        if ($filters->kind === 'epic') {
            $q->where('type', 'epic');
        }
        if ($filters->kind === 'parent') {
            $q->where('type', '!=', 'epic')->whereNull('parent_issue_id');
        }
        if ($filters->exclude !== '') {
            $q->where('public_id', '!=', $filters->exclude);
        }
        if ($filters->pagination->search !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->pagination->search)).'%';
            $q->where(fn ($q) => $q->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$term])->orWhereRaw("LOWER(identifier) LIKE ? ESCAPE '!'", [$term]));
        }

        return $q->orderBy('identifier')->orderBy('public_id')->paginate($filters->pagination->perPage,
            ['id', 'public_id', 'identifier', 'title', 'type', 'state_id', 'epic_id', 'parent_issue_id'], 'page', $filters->pagination->page);
    }

    /**
     * 讀取指定任務描述與必要關聯，活動另行取得。
     *
     * @param  Project  $project  已授權Project
     * @param  string  $id  Issue公開ID
     * @param  ?string  $timeUser  本人工時資格，預設不讀
     * @param  bool  $teamTime  團隊工時資格，預設false
     * @return ?Issue 存在時的任務
     */
    public function find(Project $project, string $id, ?string $timeUser = null, bool $teamTime = false): ?Issue
    {
        $q = $this->query($project)->where('public_id', $id)->with([...$this->relations(), 'epic.status:id,code', 'parent.status:id,code', 'relatedIssues' => fn ($related) => $related->select(['issues.id', 'issues.public_id', 'issues.title', 'issues.identifier', 'issues.type', 'issues.project_id', 'issues.state_id'])->with('status:id,code')->orderBy('issues.public_id')->limit(201)]);
        $this->timeProjection($q, $timeUser, $teamTime, true);

        return $q->first($this->columns(true));
    }

    /**
     * 活動入口只讀指定父任務識別，不附描述與關聯。
     *
     * @param  Project  $project  已授權Project
     * @param  string  $id  Issue公開ID
     * @return ?Issue 存在時的最小父列
     */
    public function reference(Project $project, string $id): ?Issue
    {
        return $this->query($project)->where('public_id', $id)->first(['id', 'workspace_id', 'project_id', 'public_id', 'type']);
    }

    /**
     * 取得指定父任務的留言分頁，不查其他任務內容。
     *
     * @param  Issue  $issue  已授權父任務
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 留言分頁
     */
    public function comments(Issue $issue, PageQueryData $filters): LengthAwarePaginator
    {
        return DB::table('issue_comments')->where('issue_id', $issue->id)->orderBy('position')->orderBy('id')
            ->paginate($filters->perPage, ['public_id as id', 'content', 'author', 'created_at'], 'page', $filters->page);
    }

    /**
     * 讀取指定租戶及任務的異動分頁，依position及ID穩定排序。
     *
     * @param  Issue  $issue  已授權父任務
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 異動分頁
     */
    public function history(Issue $issue, PageQueryData $filters): LengthAwarePaginator
    {
        return DB::table('audit_events')->where('workspace_id', $issue->workspace_id)->where('entity_type', 'issues')
            ->where('entity_id', $issue->id)->where('stream', 'history')->orderByDesc('position')->orderByDesc('id')
            ->paginate($filters->perPage, ['event_data'], 'page', $filters->page);
    }

    /**
     * 只查同Workspace／Project下的直接所屬卡片，不附描述或活動。
     *
     * @param  Issue  $issue  已授權父任務
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator<Issue> 按識別與ID穩定排序的摘要
     */
    public function children(Issue $issue, PageQueryData $filters): LengthAwarePaginator
    {
        return Issue::where('workspace_id', $issue->workspace_id)->where('project_id', $issue->project_id)
            ->where($issue->type === 'epic' ? 'epic_id' : 'parent_issue_id', $issue->id)
            ->with($this->relations())->orderBy('identifier')->orderBy('public_id')
            ->paginate($filters->perPage, $this->columns(), 'page', $filters->page);
    }

    /**
     * 指定任務及Epic所屬卡片的有效工時，在SQL依資格與日期分頁。
     *
     * @param  Issue  $issue  已授權任務
     * @param  string  $userId  可信User ID
     * @param  bool  $team  是否可讀團隊工時
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 不含歷史的有效工時分頁
     */
    public function time(Issue $issue, string $userId, bool $team, PageQueryData $filters): LengthAwarePaginator
    {
        return DB::table('time_entries as entry')->join('issues as target', 'target.id', '=', 'entry.issue_id')
            ->join('projects as source_project', 'source_project.id', '=', 'entry.project_id')
            ->join('workspace_members as author', 'author.id', '=', 'entry.member_id')->join('users', 'users.id', '=', 'author.user_id')
            ->where('entry.workspace_id', $issue->workspace_id)->where('entry.project_id', $issue->project_id)->whereNull('entry.voided_at')
            ->where(fn ($q) => $q->where('target.id', $issue->id)->when($issue->type === 'epic', fn ($q) => $q->orWhere('target.epic_id', $issue->id)))
            ->when(! $team, fn ($q) => $q->where('users.id', $userId))
            ->orderByDesc('entry.work_date')->orderByDesc('entry.created_at')->orderByDesc('entry.id')
            ->paginate($filters->perPage, ['entry.public_id as id', 'source_project.public_id as projectId', 'target.public_id as issueId', 'target.identifier as issueIdentifier', 'target.title as issueTitle', 'target.type as issueType', 'users.id as userId', 'users.name as userName', 'entry.work_date as workDate', 'entry.minutes', 'entry.note', 'entry.revision', 'entry.created_at', 'entry.updated_at'], 'page', $filters->page);
    }
}
