<?php

namespace App\Repositories;

use App\Data\IssueOptionQueryData;
use App\Data\IssueQueryData;
use App\Data\PageQueryData;
use App\Database\DatabaseTimes;
use App\Models\Issue;
use App\Models\Project;
use App\Queries\BoardIssueScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        $q->select($this->columns($detail));
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
        $this->applyFilters($q, $userId, $filters);
        // TT-306：四種排序都能換方向；沒指定時更新／建立新到舊, 優先級緊急到低, 到期日近到遠。
        // 方向只來自allowlist驗證後的asc／desc, 再對照一次才放進SQL；同時間的卡用卡號、公開ID排, 換頁不重複不漏。
        $direction = ['asc' => 'asc', 'desc' => 'desc'][$filters->direction] ?? (in_array($filters->sort, ['priority', 'due'], true) ? 'asc' : 'desc');
        if ($filters->sort === 'priority') {
            $q->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END ".$direction);
        } elseif ($filters->sort === 'due') {
            // 沒填到期日的卡照方向走：近到遠時排最後, 遠到近時排最前。
            $q->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END '.$direction)->orderBy('due_date', $direction);
        } elseif ($filters->sort === 'created') {
            $q->orderBy('created_at', $direction);
        }
        $q->orderBy('updated_at', $filters->sort === 'updated' ? $direction : 'desc');

        $page = $q->orderBy('identifier')->orderBy('public_id')
            ->paginate($filters->pagination->perPage, $this->columns(), 'page', $filters->pagination->page);
        if (! $listOnly) {
            $this->loadEpicEstimates($project, $page->items());
        }

        return $page;
    }

    /**
     * 只對當頁實際出現的Epic批次加總, 一般卡片不執行估時子查詢
     *
     * @param  Project  $project  已授權且限定工作區的專案
     * @param  list<Issue>  $issues  當頁至多100筆或單卡詳細
     * @return void
     */
    private function loadEpicEstimates(Project $project, array $issues): void
    {
        $epics = array_values(array_filter($issues, fn (Issue $issue) => $issue->type === 'epic'));
        if ($epics === []) {
            return;
        }
        $totals = $this->query($project)->whereIn('epic_id', array_map(fn (Issue $issue) => $issue->id, $epics))
            ->select('epic_id')->selectRaw('SUM(estimate_minutes) AS total')->groupBy('epic_id')->pluck('total', 'epic_id');
        foreach ($epics as $epic) {
            $epic->setAttribute('epic_estimate_minutes', (int) ($totals[$epic->id] ?? 0));
        }
    }

    /**
     * 共用篩選只組合已限定Project的SQL，不載入任務或承擔業務授權。
     *
     * @param  Builder  $q  已限定租戶／Project
     * @param  string  $userId  可信操作者
     * @param  IssueQueryData  $filters  已驗證篩選
     * @return void
     */
    private function applyFilters(Builder $q, string $userId, IssueQueryData $filters): void
    {
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
        $this->applySearch($q, $filters->pagination->search, true);

    }

    /** 卡號走索引, 其他文字保留字面contains；不把FULLTEXT近似結果當原本語意
     * @param  Builder  $q  已限定Project的查詢
     * @param  string  $search  已驗證搜尋
     * @param  bool  $full  是否包含描述與承辦者
     * @return void
     */
    private function applySearch(Builder $q, string $search, bool $full): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,11}-[1-9][0-9]*$/D', $search)) {
            $q->where('identifier', strtoupper($search));

            return;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,11}-[0-9]*\*$/D', $search)) {
            $prefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], strtoupper(substr($search, 0, -1)));
            $q->whereRaw("identifier LIKE ? ESCAPE '!'", [$prefix.'%']);

            return;
        }
        $literal = str_starts_with(mb_strtolower($search), 'text:') ? substr($search, 5) : $search;
        $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($literal)).'%';
        $q->where(function (Builder $query) use ($term, $full) {
            $query->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$term])->orWhereRaw("LOWER(identifier) LIKE ? ESCAPE '!'", [$term]);
            if ($full) {
                $query->orWhereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereHas('assignee.user', fn ($q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]));
            }
        });
    }

    /**
     * 時間＋唯一ID keyset；多讀一筆判斷續頁，禁止COUNT／OFFSET或活動聚合。
     *
     * @param  Project  $project  已授權Project
     * @param  string  $userId  可信操作者
     * @param  IssueQueryData  $filters  共用篩選
     * @param  ?string  $since  包含邊界水位；null包含歷史無日期資料
     * @param  string  $until  固定本輪截止水位
     * @param  ?array  $after  上一批實際DB時間及內部ID
     * @return Collection<int,Issue> 至多perPage+1筆最小摘要
     */
    public function changes(Project $project, string $userId, IssueQueryData $filters, ?string $since, string $until, ?array $after): Collection
    {
        $q = $this->query($project)->select(['id', 'workspace_id', 'public_id', 'project_id', 'identifier', 'title', 'type', 'state_id', 'priority', 'assignee_id', 'due_date', 'revision', 'created_at', 'updated_at'])
            ->with(['project:id,public_id', 'status:id,code', 'assignee:id,user_id', 'assignee.user:id,name']);
        $this->applyFilters($q, $userId, $filters);
        $q->where(fn ($q) => $q->where('updated_at', '<=', DatabaseTimes::store($until))->orWhereNull('updated_at'));
        if ($since !== null) {
            $q->where('updated_at', '>=', DatabaseTimes::store($since));
        }
        // TT-306：預設舊到新（適合增量同步）；desc從最新往回讀, cursor比較方向跟著反過來。
        $desc = $filters->direction === 'desc';
        if ($after !== null) {
            $q->where(function ($q) use ($after, $desc) {
                if ($desc) {
                    // 倒序時沒有更新時間的卡排在最後。
                    if ($after['time'] === null) {
                        $q->whereNull('updated_at')->where('id', '<', $after['id']);
                    } else {
                        $q->where('updated_at', '<', $after['time'])->orWhereNull('updated_at')->orWhere(fn ($q) => $q->where('updated_at', $after['time'])->where('id', '<', $after['id']));
                    }
                } elseif ($after['time'] === null) {
                    $q->whereNotNull('updated_at')->orWhere(fn ($q) => $q->whereNull('updated_at')->where('id', '>', $after['id']));
                } else {
                    $q->where('updated_at', '>', $after['time'])->orWhere(fn ($q) => $q->where('updated_at', $after['time'])->where('id', '>', $after['id']));
                }
            });
        }

        return $q->with(['externalRefs' => fn ($refs) => $refs->select(['id', 'issue_id', 'source', 'external_id'])->orderBy('source')])->orderBy('updated_at', $desc ? 'desc' : 'asc')->orderBy('id', $desc ? 'desc' : 'asc')->limit($filters->pagination->perPage + 1)->get();
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
        $this->applySearch($q, $filters->pagination->search, false);

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

        $issue = $q->first($this->columns(true));
        if ($issue !== null) {
            $this->loadEpicEstimates($project, [$issue]);
        }

        return $issue;
    }

    /**
     * 舊版無Project深連結只解析所屬Project公開ID，不讀卡片內容。
     *
     * @param  string  $workspaceId  可信Workspace公開ID
     * @param  string  $id  Issue公開ID
     * @return ?string 同Workspace內的Project公開ID
     */
    public function locate(string $workspaceId, string $id): ?string
    {
        return Issue::query()->join('projects as p', fn ($join) => $join->on('p.id', '=', 'issues.project_id')->on('p.workspace_id', '=', 'issues.workspace_id'))
            ->join('workspaces as w', 'w.id', '=', 'issues.workspace_id')
            ->where('w.public_id', $workspaceId)->where('issues.public_id', $id)->value('p.public_id');
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
