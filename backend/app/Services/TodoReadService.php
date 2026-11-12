<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Data\PageQueryData;
use App\Data\PersonalReadContext;
use App\Data\TodoPageData;
use App\Data\TodoQueryData;
use App\Domain\DomainError;
use App\Models\Todo;
use App\Repositories\TodoReadRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 個人待辦讀取與當次操作能力；HTTP與後續Token入口可共用，不保存actor狀態。 */
class TodoReadService
{
    /**
     * 注入個人資料入口與批次Project授權。
     *
     * @param  TodoReadRepository  $todos  有界個人查詢
     * @param  ProjectReadService  $projects  Project有效permission入口
     */
    public function __construct(private TodoReadRepository $todos, private ProjectReadService $projects) {}

    /**
     * 在同一用例重新驗證個人讀取資格並取得內部鍵。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @return PersonalReadContext 當次讀取範圍
     *
     * @throws DomainError 失去待辦或工作區資格
     */
    private function context(array $actor): PersonalReadContext
    {
        WorkspacePermissions::authorize($actor, 'todo.read');
        $member = $this->todos->member($actor['workspace']['id'], $actor['user']['id']) ?? DomainError::fail('UNAUTHENTICATED', '工作空間資格已失效');

        return new PersonalReadContext($member->workspace_id, $member->id, $actor['user']['id'], (int) $member->role_revision);
    }

    /**
     * 提供摘要分頁，轉卡引用在當頁範圍內批次授權／取得。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  TodoQueryData  $filters  有界個人列表條件
     * @return TodoPageData 摘要與當次能力
     */
    public function index(array $actor, TodoQueryData $filters): TodoPageData
    {
        $context = $this->context($actor);
        $page = $this->todos->page($context, $filters);
        $caps = [];
        foreach ($page->items() as $todo) {
            $caps[$todo->public_id] = $this->capabilities($todo, $context, in_array('todo.writeOwn', $actor['permissions'], true));
        }

        return new TodoPageData($page, $this->links($actor, $context, $page->items()), $caps);
    }

    /**
     * 只讀可見單待辦原稿，不隱含歷史或其他待辦。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  string  $id  Todo公開ID
     * @return array<string,mixed> 單待辦及授權投影
     */
    public function show(array $actor, string $id): array
    {
        $context = $this->context($actor);
        $todo = $this->todos->find($context, $id) ?? DomainError::fail('NOT_FOUND', '找不到這筆待辦');

        return ['todo' => $todo, 'links' => $this->links($actor, $context, [$todo]), 'capabilities' => $this->capabilities($todo, $context, in_array('todo.writeOwn', $actor['permissions'], true))];
    }

    /**
     * 限於參與者的單待辦歷史分頁。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  string  $id  Todo公開ID
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 歷史事件分頁
     */
    public function history(array $actor, string $id, PageQueryData $filters): LengthAwarePaginator
    {
        $todo = $this->todos->find($this->context($actor), $id, false) ?? DomainError::fail('NOT_FOUND', '找不到這筆待辦');

        return $this->todos->history($todo, $filters);
    }

    /**
     * 只取得個人清單數量，不附列表內容或通知。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @return array<string,int> 有效個人數量
     */
    public function counts(array $actor): array
    {
        return $this->todos->counts($this->context($actor));
    }

    /**
     * 只取得收件人仍可見的未讀通知數。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @return array{unread:int} 未讀摘要
     */
    public function unread(array $actor): array
    {
        return ['unread' => $this->todos->unread($this->context($actor))];
    }

    /**
     * 依當次收件人／參與者交集取得通知分頁。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  PageQueryData  $filters  有界分頁
     * @return LengthAwarePaginator 通知分頁
     */
    public function notifications(array $actor, PageQueryData $filters): LengthAwarePaginator
    {
        return $this->todos->notificationPage($this->context($actor), $filters);
    }

    /**
     * 只回個人待辦／通知的變更版本，不載列表或原稿。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @return array{todos:string,notifications:string} 當次有效範圍版本
     */
    public function versions(array $actor): array
    {
        return $this->todos->versions($this->context($actor));
    }

    /**
     * 按有效permission推導可接收待辦角色，再以SQL限制候選。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  PageQueryData  $filters  有界分頁／搜尋
     * @return LengthAwarePaginator 候選承辦者
     */
    public function assignees(array $actor, PageQueryData $filters): LengthAwarePaginator
    {
        $roles = array_values(array_filter(['owner', 'manager', 'admin', 'user', 'member', 'maintainer', 'developer'], fn ($role) => in_array('todo.writeOwn', WorkspacePermissions::resolve($role), true)));

        return $this->todos->assignees($this->context($actor), $roles, in_array('todo.assign', $actor['permissions'], true), $filters);
    }

    /**
     * 按當次permission及作者／承辦者／生命週期計算能力，不以角色名稱放行。
     *
     * @param  Todo  $todo  已限定參與者的待辦
     * @param  PersonalReadContext  $context  當次可信資格
     * @param  bool  $write  是否具todo.writeOwn
     * @return array<string,bool> 此筆操作能力
     */
    private function capabilities(Todo $todo, PersonalReadContext $context, bool $write): array
    {
        $creator = $todo->created_by_id === $context->memberId;
        $assignee = $todo->assignee_id === $context->memberId;
        $open = $todo->state === 'open';

        return ['edit' => $write && $creator && $open, 'cancel' => $write && $creator && $open, 'complete' => $write && $assignee && $open, 'reopen' => $write && $assignee && $todo->state === 'done', 'return' => $write && $assignee && ! $creator && $open, 'convert' => $write && $assignee && $open];
    }

    /**
     * 當頁最多100筆轉卡連結，先批次驗證Project權限後才讀Issue識別。
     *
     * @param  array<string,mixed>  $actor  當次可信操作者
     * @param  PersonalReadContext  $context  當次可信資格
     * @param  array<int,Todo>  $todos  當頁摘要或單筆詳細
     * @return array<int,array<string,string>> 可讀轉卡連結，無權則不產生
     */
    private function links(array $actor, PersonalReadContext $context, array $todos): array
    {
        $projects = array_values(array_unique(array_filter(array_map(fn ($t) => $t->converted_project_id, $todos))));
        if ($projects === []) {
            return [];
        }
        $references = $this->todos->projectReferences($context, $projects);
        $permissions = $this->projects->permissionsFor($actor, $references->pluck('public_id')->all());
        $allowed = $references->filter(fn ($p) => in_array('issue.read', $permissions[$p->public_id] ?? [], true))->keyBy('id');
        $issues = array_values(array_unique(array_filter(array_map(fn ($t) => $t->converted_issue_id, $todos))));
        if ($allowed->isEmpty()) {
            return [];
        }
        $result = [];
        foreach ($this->todos->convertedReferences($context, $allowed->keys()->all(), $issues) as $issue) {
            $result[$issue->id] = ['id' => $issue->public_id, 'projectId' => $allowed[$issue->project_id]->public_id, 'identifier' => $issue->identifier];
        }

        return $result;
    }
}
