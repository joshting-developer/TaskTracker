<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Data\PersonalReadContext;
use App\Models\Todo;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** 待辦協作資料入口, 每次查詢都限定Workspace與單一待辦 */
class TodoCollaborationRepository
{
    /** 從可信公開ID重驗有效成員並取得資料庫範圍
     * @param  string  $workspaceId  工作區公開ID
     * @param  string  $userId  可信帳號
     * @return ?WorkspaceMember 有效資格
     */
    public function member(string $workspaceId, string $userId): ?WorkspaceMember
    {
        return WorkspaceMember::whereHas('workspace', fn ($q) => $q->where('public_id', $workspaceId))->where('user_id', $userId)->whereNull('disabled_at')->first(['id', 'workspace_id', 'user_id', 'role']);
    }

    /** 鎖定單一待辦並只讀寫入所需欄位, 不預載JSON原稿或歷史
     * @param  PersonalReadContext  $context  可信範圍
     * @param  string  $id  待辦公開ID
     * @return ?Todo 目前資料
     */
    public function lock(PersonalReadContext $context, string $id): ?Todo
    {
        return Todo::where('workspace_id', $context->workspaceId)->where('public_id', $id)->lockForUpdate()->first(['id', 'workspace_id', 'public_id', 'created_by_id', 'initial_assignee_id', 'assignee_id', 'creator_name', 'assignee_name', 'state', 'revision', 'title', 'created_at']);
    }

    /** 檢查目前或過去經手資格, 不以工作區Owner身份放行
     * @param  PersonalReadContext  $context  可信範圍
     * @param  Todo  $todo  同工作區待辦
     * @return bool 是否曾經手
     */
    public function participates(PersonalReadContext $context, Todo $todo): bool
    {
        return in_array($context->memberId, [$todo->created_by_id, $todo->initial_assignee_id, $todo->assignee_id], true) || DB::table('todo_participants')->where('workspace_id', $context->workspaceId)->where('todo_id', $todo->id)->where('member_id', $context->memberId)->exists();
    }

    /** 為新建立或舊seed待辦補齊有限的初始經手與目前路徑
     * @param  Todo  $todo  已鎖定待辦
     * @return void
     */
    public function initialize(Todo $todo): void
    {
        foreach (array_unique([$todo->created_by_id, $todo->initial_assignee_id, $todo->assignee_id]) as $member) {
            $this->addParticipant($todo, $member);
        }
        if (! DB::table('todo_assignment_paths')->where('workspace_id', $todo->workspace_id)->where('todo_id', $todo->id)->exists()) {
            foreach (array_values(array_unique([$todo->created_by_id, $todo->assignee_id])) as $position => $member) {
                $this->push($todo, $position, $member);
            }
        }
    }

    /** 保存去重的經手資格, 不移除舊承辦人的資格
     * @param  Todo  $todo  已鎖定待辦
     * @param  int  $memberId  同工作區成員鍵
     * @return void
     */
    public function addParticipant(Todo $todo, int $memberId): void
    {
        DB::table('todo_participants')->insertOrIgnore(['workspace_id' => $todo->workspace_id, 'todo_id' => $todo->id, 'member_id' => $memberId]);
    }

    /** 只讀交接路徑的最後兩筆, 不載入完整歷史
     * @param  Todo  $todo  同工作區待辦
     * @return array<int,object> 由新到舊的兩個位置
     */
    public function tail(Todo $todo): array
    {
        return DB::table('todo_assignment_paths')->where('workspace_id', $todo->workspace_id)->where('todo_id', $todo->id)->orderByDesc('position')->limit(2)->get()->all();
    }

    /** 加入新的目前承辦位置
     * @param  Todo  $todo  已鎖定待辦
     * @param  int  $position  下一個位置
     * @param  int  $memberId  同工作區成員
     * @return void
     */
    public function push(Todo $todo, int $position, int $memberId): void
    {
        DB::table('todo_assignment_paths')->insert(['workspace_id' => $todo->workspace_id, 'todo_id' => $todo->id, 'position' => $position, 'member_id' => $memberId]);
    }

    /** 退回上一手時只移除目前的路徑位置, 不刪經手歷史
     * @param  Todo  $todo  已鎖定待辦
     * @param  int  $position  目前位置
     * @return void
     */
    public function pop(Todo $todo, int $position): void
    {
        DB::table('todo_assignment_paths')->where('workspace_id', $todo->workspace_id)->where('todo_id', $todo->id)->where('position', $position)->delete();
    }

    /** 保存Service指定的留言或交接事件
     * @param  string  $table  固定白名單資料表
     * @param  array<string,mixed>  $data  已驗證欄位
     * @return void
     */
    public function append(string $table, array $data): void
    {
        if (! in_array($table, ['todo_comments', 'todo_handoffs'], true)) {
            throw new \LogicException('Invalid collaboration table');
        }
        DB::table($table)->insert($data);
    }

    /** 保存同一待辦的版本與狀態異動
     * @param  Todo  $todo  已鎖定待辦
     * @param  array<string,mixed>  $data  已驗證屬性
     * @return void
     */
    public function update(Todo $todo, array $data): void
    {
        $todo->forceFill($data)->save();
    }

    /** 依時間與ID穩定分頁, 不把所有留言或交接打包到列表
     * @param  Todo  $todo  已授權待辦
     * @param  string  $table  固定資料表
     * @param  PageQueryData  $page  有界分頁
     * @return LengthAwarePaginator 當頁事件
     */
    public function page(Todo $todo, string $table, PageQueryData $page): LengthAwarePaginator
    {
        if (! in_array($table, ['todo_comments', 'todo_handoffs'], true)) {
            throw new \LogicException('Invalid collaboration table');
        }
        $columns = $table === 'todo_comments' ? ['public_id', 'author_id', 'author_name', 'content', 'created_at'] : ['public_id', 'from_id', 'to_id', 'from_name', 'to_name', 'kind', 'created_at'];

        return DB::table($table)->where('workspace_id', $todo->workspace_id)->where('todo_id', $todo->id)->orderBy('created_at')->orderBy('id')->paginate($page->perPage,$columns,'page',$page->page);
    }
}
