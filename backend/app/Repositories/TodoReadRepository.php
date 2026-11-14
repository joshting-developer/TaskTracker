<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Data\PersonalReadContext;
use App\Data\TodoQueryData;
use App\Models\Issue;
use App\Models\Todo;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** 在SQL先限定Workspace及參與者，再執行待辦／通知的投影與分頁。 */
class TodoReadRepository
{
    /**
     * 只查可信User在可信Workspace的有效資格鍵。
     *
     * @param  string  $workspaceId  Workspace公開ID
     * @param  string  $userId  可信User ID
     * @return WorkspaceMember|null 有效資格
     */
    public function member(string $workspaceId, string $userId): ?WorkspaceMember
    {
        return WorkspaceMember::whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))->where('user_id', $userId)->whereNull('disabled_at')->first(['id', 'workspace_id', 'role_revision']);
    }

    /**
     * 建立僅本人參與的查詢，後續不可取消此範圍。
     *
     * @param  PersonalReadContext  $context  當次可信資格
     * @return Builder<Todo> 尚未執行的參與者限定查詢
     */
    private function query(PersonalReadContext $context): Builder
    {
        return $this->participantScope(Todo::where('workspace_id', $context->workspaceId), $context, 'todos');
    }

    /**
     * 待辦摘要只讀當頁；不載原稿、note、history或轉卡Issue內容。
     *
     * @param  PersonalReadContext  $context  當次可信資格
     * @param  TodoQueryData  $filters  有界列表條件
     * @return LengthAwarePaginator<Todo> 摘要分頁
     */
    public function page(PersonalReadContext $context, TodoQueryData $filters): LengthAwarePaginator
    {
        $q = $this->query($context);
        if ($filters->scope === 'mine') {
            $q->where('assignee_id', $context->memberId);
        } elseif ($filters->scope === 'sent') {
            $q->where('created_by_id', $context->memberId);
        }
        if ($filters->state === 'open') {
            $q->where('state', 'open');
        } elseif ($filters->state === 'closed') {
            $q->where('state', '!=', 'open');
        }
        if ($filters->pagination->search !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->pagination->search)).'%';
            $q->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$term]);
        }

        return $q->select($this->columns())->selectRaw("((COALESCE(content, '') <> '' AND content <> title) OR COALESCE(note, '') <> '') as has_details")->withExists(['assignmentPaths as has_return_path' => fn ($q) => $q->where('position', '>', 0)])->with(['creator:id,user_id', 'assignee:id,user_id', 'initialAssignee:id,user_id', 'initialAssignee.user:id,name'])->orderByDesc('updated_at')->orderBy('public_id')->paginate($filters->pagination->perPage, $this->columns(), 'page', $filters->pagination->page);
    }

    /**
     * 明確列出摘要欄位，避免Eloquent預設select *。
     *
     * @return array<int,string> 摘要投影
     */
    private function columns(): array
    {
        return ['id', 'workspace_id', 'public_id', 'title', 'created_by_id', 'creator_name', 'assignee_id', 'assignee_name', 'initial_assignee_id', 'state', 'revision', 'created_at', 'updated_at', 'returned_at', 'completed_at', 'converted_project_id', 'converted_issue_id'];
    }

    /**
     * 開單待辦才讀原稿，仍不附全部歷史。
     *
     * @param  PersonalReadContext  $context  當次可信資格
     * @param  string  $id  Todo公開ID
     * @param  bool  $detail  是否讀原稿，活動入口為false
     * @return Todo|null 可讀待辦
     */
    public function find(PersonalReadContext $context, string $id, bool $detail = true): ?Todo
    {
        return $this->query($context)->where('public_id', $id)->select([...$this->columns(), ...($detail ? ['content', 'note'] : [])])->selectRaw("((COALESCE(content, '') <> '' AND content <> title) OR COALESCE(note, '') <> '') as has_details")->withExists(['assignmentPaths as has_return_path' => fn ($q) => $q->where('position', '>', 0)])->with(['creator:id,user_id', 'assignee:id,user_id', 'initialAssignee:id,user_id', 'initialAssignee.user:id,name'])
            ->first([...$this->columns(), ...($detail ? ['content', 'note'] : [])]);
    }

    /**
     * 本人參與的開啟／結束數量由SQL聚合，不讀待辦集合。
     *
     * @param  PersonalReadContext  $context  當次可信資格
     * @return array<string,int> 兩種範圍的開啟與結束數量
     */
    public function counts(PersonalReadContext $context): array
    {
        $row = $this->query($context)->selectRaw('COALESCE(SUM(CASE WHEN assignee_id = ? AND state = ? THEN 1 ELSE 0 END),0) AS mine_open, COALESCE(SUM(CASE WHEN assignee_id = ? AND state != ? THEN 1 ELSE 0 END),0) AS mine_closed, COALESCE(SUM(CASE WHEN created_by_id = ? AND state = ? THEN 1 ELSE 0 END),0) AS sent_open, COALESCE(SUM(CASE WHEN created_by_id = ? AND state != ? THEN 1 ELSE 0 END),0) AS sent_closed, COALESCE(SUM(CASE WHEN state = ? THEN 1 ELSE 0 END),0) AS involved_open, COALESCE(SUM(CASE WHEN state != ? THEN 1 ELSE 0 END),0) AS involved_closed', [$context->memberId, 'open', $context->memberId, 'open', $context->memberId, 'open', $context->memberId, 'open', 'open', 'open'])->first();

        return ['mineOpen' => (int) $row->mine_open, 'mineClosed' => (int) $row->mine_closed, 'sentOpen' => (int) $row->sent_open, 'sentClosed' => (int) $row->sent_closed, 'involvedOpen' => (int) $row->involved_open, 'involvedClosed' => (int) $row->involved_closed];
    }

    /**
     * 只列可接待辦的有效資格，姓名／email供使用者區分同名對象。
     *
     * @param  PersonalReadContext  $context  當次可信資格
     * @param  array<int,string>  $roles  經permission映射取得的可接收角色ID
     * @param  bool  $assign  是否具有todo.assign
     * @param  PageQueryData  $filters  有界搜尋與分頁
     * @return LengthAwarePaginator 可選承辦者分頁
     */
    public function assignees(PersonalReadContext $context, array $roles, bool $assign, PageQueryData $filters): LengthAwarePaginator
    {
        $q = DB::table('workspace_members as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.workspace_id', $context->workspaceId)->whereNull('m.disabled_at')->whereIn('m.role', $roles);
        if (! $assign) {
            $q->where('m.user_id', $context->userId);
        }
        if ($filters->search !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->search)).'%';
            $q->where(fn ($q) => $q->whereRaw("LOWER(u.name) LIKE ? ESCAPE '!'", [$term])->orWhereRaw("LOWER(u.email) LIKE ? ESCAPE '!'", [$term]));
        }

        return $q->orderBy('u.name')->orderBy('u.id')->paginate($filters->perPage, ['u.id', 'u.name', 'u.email'], 'page', $filters->page);
    }

    /**
     * 所屬卡片只供批次授權前解析Project公開ID，不讀名稱或其他內容。
     *
     * @param  PersonalReadContext  $context  當次可信資格
     * @param  array<int,int>  $ids  至多100個Project內部鍵
     * @return Collection<int,object> 最小Project對照
     */
    public function projectReferences(PersonalReadContext $context, array $ids): Collection
    {
        if (count($ids) > 100) {
            throw new \LogicException('Project reference batch exceeds 100');
        }

        return DB::table('projects')->where('workspace_id', $context->workspaceId)->whereIn('id', $ids)->limit(count($ids))->get(['id', 'public_id']);
    }

    /**
     * 僅讀已通過issue.read的Project中、當頁轉卡引用的最小Issue參照。
     *
     * @param  PersonalReadContext  $context  當次可信資格
     * @param  array<int,int>  $projectIds  已授權Project內部鍵
     * @param  array<int,int>  $issueIds  當頁轉卡Issue內部鍵
     * @return Collection<int,Issue> 最小卡片連結
     */
    public function convertedReferences(PersonalReadContext $context, array $projectIds, array $issueIds): Collection
    {
        if (count($issueIds) > 100 || count($projectIds) > 100) {
            throw new \LogicException('Converted reference batch exceeds 100');
        }

        return Issue::where('workspace_id', $context->workspaceId)->whereIn('project_id', $projectIds)->whereIn('id', $issueIds)->limit(count($issueIds))->get(['id', 'project_id', 'public_id', 'identifier']);
    }

    /**
     * 只讀單待辦history分頁，保留事件快照而不載整條stream。
     *
     * @param  Todo  $todo  當次已授權待辦
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 歷史分頁
     */
    public function history(Todo $todo, PageQueryData $filters): LengthAwarePaginator
    {
        return DB::table('audit_events')->where('workspace_id', $todo->workspace_id)->where('entity_type', 'todos')->where('entity_id', $todo->id)->where('stream', 'history')->orderByDesc('position')->orderByDesc('id')->paginate($filters->perPage, ['event_data'], 'page', $filters->page);
    }

    /**
     * 以recipient及Todo參與者的交集限定通知，退回後原承辦者無法讀取。
     *
     * @param  PersonalReadContext  $context  當次可信資格
     * @return QueryBuilder 尚未執行的通知查詢
     */
    private function notifications(PersonalReadContext $context): QueryBuilder
    {
        return DB::table('todo_notifications as n')->join('todos as t', fn ($join) => $join->on('t.id', '=', 'n.todo_id')->on('t.workspace_id', '=', 'n.workspace_id'))
            ->where('n.workspace_id', $context->workspaceId)->where('n.recipient_id', $context->memberId)->where(fn ($q) => $this->participantScope($q, $context, 't'));
    }

    /**
     * 通知摘要只執行COUNT，不附通知或Todo內容。
     *
     * @param  PersonalReadContext  $context  當次可信資格
     * @return int 有效未讀通知數
     */
    public function unread(PersonalReadContext $context): int
    {
        return $this->notifications($context)->whereNull('n.read_at')->count();
    }

    /**
     * 以SQL聚合產生不含內容的個人變更指紋，供備援只在變動時重讀。
     *
     * @param  PersonalReadContext  $context  當次可信資格
     * @return array{todos:string,notifications:string} 不含業務內容的版本指紋
     */
    public function versions(PersonalReadContext $context): array
    {
        $todos = $this->query($context)->selectRaw('COUNT(*) AS total, COALESCE(SUM(revision),0) AS revisions, MAX(updated_at) AS latest')->first();
        $notifications = $this->notifications($context)->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN n.read_at IS NULL THEN 1 ELSE 0 END),0) AS unread, MAX(n.created_at) AS latest')->first();

        $access = DB::table('project_members as pm')->where('pm.workspace_id', $context->workspaceId)->where('pm.member_id', $context->memberId)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('todos as linked')->whereColumn('linked.workspace_id', 'pm.workspace_id')->whereColumn('linked.converted_project_id', 'pm.project_id')->where(fn ($q) => $this->participantScope($q, $context, 'linked')))
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(pm.revision),0) AS revisions, MAX(pm.updated_at) AS latest')->first();

        return ['todos' => hash('sha256', json_encode([(int) $todos->total, (int) $todos->revisions, $todos->latest, $context->roleRevision, (int) $access->total, (int) $access->revisions, $access->latest], JSON_THROW_ON_ERROR)),
            'notifications' => hash('sha256', json_encode([(int) $notifications->total, (int) $notifications->unread, $notifications->latest], JSON_THROW_ON_ERROR))];
    }

    /**
     * 按收件人資格分頁讀通知，沒有待辦原稿或歷史。
     *
     * @param  PersonalReadContext  $context  當次可信資格
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 通知分頁
     */
    public function notificationPage(PersonalReadContext $context, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->notifications($context)->orderByDesc('n.created_at')->orderByDesc('n.public_id')->paginate($filters->perPage, ['n.public_id as id', 't.public_id as todoId', 'n.actor', 'n.event', 'n.read_at', 'n.created_at', 't.title'], 'page', $filters->page);
    }

    /** 只加入既有或曾經手條件, correlated EXISTS使用覆蓋索引, 不讀取成員集合
     * @param  Builder|QueryBuilder  $query  已有限定工作區的查詢
     * @param  PersonalReadContext  $context  可信成員範圍
     * @param  string  $alias  固定資料表別名
     * @return Builder|QueryBuilder 同一查詢
     */
    private function participantScope(Builder|QueryBuilder $query, PersonalReadContext $context, string $alias): Builder|QueryBuilder
    {
        $ids = DB::table('todo_participants')->where('workspace_id', $context->workspaceId)->where('member_id', $context->memberId)->select('todo_id');
        foreach (['created_by_id', 'assignee_id', 'initial_assignee_id'] as $column) {
            $ids->union(DB::table('todos')->where('workspace_id', $context->workspaceId)->where($column, $context->memberId)->select('id'));
        }

        return $query->whereIn($alias.'.id', $ids);
    }
}
