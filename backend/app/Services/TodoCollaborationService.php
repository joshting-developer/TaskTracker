<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Data\PageQueryData;
use App\Data\PersonalReadContext;
use App\Data\WorkspaceContext;
use App\Database\DatabaseTimes;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Repositories\RecordStore;
use App\Repositories\TodoCollaborationRepository;
use Illuminate\Support\Str;

/** 待辦留言、完成附回覆及交接用例, 外層Session／PAT交易擁有寫入鎖 */
class TodoCollaborationService
{
    public const OPERATIONS = ['commentTodo', 'forwardTodo', 'completeTodo', 'returnTodo'];

    /** 注入協作資料入口與既有防重播用例
     * @param  TodoCollaborationRepository  $todos  單筆有界資料入口
     * @param  RequestReplay  $replay  同requestId及原始輸入重播
     */
    public function __construct(private TodoCollaborationRepository $todos, private RequestReplay $replay) {}

    /** 重新驗證經手資格, 用於通知讀取及相容寫入邊界
     * @param  array<string,mixed>  $identity  可信工作區身分
     * @param  string  $id  待辦公開ID
     * @return bool 是否可讀
     */
    public function canRead(array $identity, string $id): bool
    {
        $context = $this->context($identity);
        $todo = $this->todos->read($context, $id);

        return $todo && $this->todos->participates($context, $todo);
    }

    /** 新待辦保存後建立有限初始經手資格, 不讀歷史
     * @param  array<string,mixed>  $identity  可信工作區身分
     * @param  string  $id  新待辦公開ID
     * @return void
     */
    public function initialize(array $identity, string $id): void
    {
        $todo = $this->todos->lock($this->context($identity), $id) ?? DomainError::fail('NOT_FOUND', '找不到待辦');
        $this->todos->initialize($todo);
    }

    /** 在公開receipt重播前重驗當次permission及參與資格
     * @param  WorkspaceContext  $identity  交易內重驗的身分
     * @param  string  $operation  寫入操作
     * @param  ?string  $id  既有待辦ID, 建立時為null
     * @return void
     */
    public function authorizeWrite(WorkspaceContext $identity, string $operation, ?string $id): void
    {
        $legacy = $identity->legacyIdentity();
        $context = $this->context($legacy);
        WorkspacePermissions::authorize($legacy, 'todo.writeOwn');
        if ($operation === 'forwardTodo') {
            WorkspacePermissions::authorize($legacy, 'todo.assign');
        }
        if ($id !== null) {
            $todo = $this->todos->read($context, $id);
            if (! $todo || ! $this->todos->participates($context, $todo)) {
                DomainError::fail('NOT_FOUND', '找不到待辦');
            }
        }
    }

    /** 讀取已授權單筆的留言或交接分頁, 不塞進列表
     * @param  WorkspaceContext  $identity  當次可信身分
     * @param  string  $id  待辦公開ID
     * @param  string  $kind  comments或handoffs
     * @param  PageQueryData  $filters  已驗證的有界分頁
     * @return array<string,mixed> 事件分頁與最小欄位
     */
    public function events(WorkspaceContext $identity, string $id, string $kind, PageQueryData $filters): array
    {
        $context = $this->context($identity->legacyIdentity());
        $todo = $this->todos->read($context, $id) ?? DomainError::fail('NOT_FOUND', '找不到待辦');
        if (! $this->todos->participates($context, $todo)) {
            DomainError::fail('NOT_FOUND', '找不到待辦');
        }
        $table = match ($kind) {
            'comments' => 'todo_comments','handoffs' => 'todo_handoffs',default => DomainError::fail('NOT_FOUND', '找不到事件種類')
        };
        $page = $this->todos->page($todo, $table, $filters);
        $items = array_map(fn ($row) => $kind === 'comments' ? ['id' => $row->public_id, 'author' => $row->author_name, 'content' => $row->content, 'createdAt' => DatabaseTimes::public($row->created_at)] : ['id' => $row->public_id, 'from' => $row->from_name, 'to' => $row->to_name, 'kind' => $row->kind, 'createdAt' => DatabaseTimes::public($row->created_at)], $page->items());

        return ['items' => $items, 'page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => max(1, $page->lastPage())];
    }

    /** 經手資格及有效permission先於重播, 新執行另檢查承辦者與版本
     * @param  array<string,mixed>  $identity  當次可信身分
     * @param  RecordStore  $store  已限定工作區的receipt入口
     * @param  string  $operation  固定操作
     * @param  array<string,mixed>  $input  原始簽章輸入
     * @return array{id:string,revision:int} 最小寫入結果
     */
    public function execute(array $identity, RecordStore $store, string $operation, array $input): array
    {
        if (! in_array($operation, self::OPERATIONS, true)) {
            DomainError::fail('NOT_FOUND', '找不到操作');
        }
        $context = $this->context($identity);
        WorkspacePermissions::authorize($identity, 'todo.writeOwn');
        if ($operation === 'forwardTodo') {
            WorkspacePermissions::authorize($identity, 'todo.assign');
        }
        $todo = $this->todos->lock($context, Fields::text($input['id'] ?? null, '待辦', 100, true)) ?? DomainError::fail('NOT_FOUND', '找不到待辦');
        if (! $this->todos->participates($context, $todo)) {
            DomainError::fail('NOT_FOUND', '找不到待辦');
        }

        return $this->replay->execute($store, 'todoRequests', $context->userId, $operation, $input, function () use ($context, $identity, $operation, $input, $todo) {
            if (($input['expectedRevision'] ?? null) !== (int) $todo->revision) {
                DomainError::fail('REVISION_CONFLICT', '待辦已更新, 請載入最新內容');
            }
            if ($operation !== 'commentTodo' && $todo->assignee_id !== $context->memberId) {
                DomainError::fail('FORBIDDEN', '只有目前承辦人能執行這個操作');
            }
            if ($operation === 'returnTodo' && $todo->assignee_id === $todo->created_by_id && count($this->todos->tail($todo)) < 2) {
                DomainError::fail('FORBIDDEN', '沒有可退回的上一手');
            }
            if ($operation !== 'commentTodo' && $todo->state !== 'open') {
                DomainError::fail('INVALID_INPUT', '待辦已不是待處理狀態');
            }
            $reply = Fields::text($input['reply'] ?? ($operation === 'commentTodo' ? ($input['content'] ?? null) : ($input['note'] ?? '')), '回覆', 2000, $operation === 'commentTodo');
            $this->todos->initialize($todo);
            $at = now();
            $actor = $identity['user']['name'];
            if ($reply !== '') {
                $this->todos->append('todo_comments', ['public_id' => (string) Str::uuid(), 'workspace_id' => $todo->workspace_id, 'todo_id' => $todo->id, 'author_id' => $context->memberId, 'author_name' => $actor, 'content' => $reply, 'created_at' => $at]);
            }
            $changes = [];
            $event = match ($operation) {
                'commentTodo' => 'commented','forwardTodo' => 'forwarded','returnTodo' => 'returned','completeTodo' => 'completed'
            };
            $recipient = $todo->created_by_id === $context->memberId ? $todo->assignee_id : $todo->created_by_id;
            if (in_array($operation, ['forwardTodo', 'returnTodo'], true)) {
                $tail = $this->todos->tail($todo);
                if ($operation === 'forwardTodo') {
                    $target = $this->todos->member($identity['workspace']['id'], Fields::text($input['assigneeId'] ?? null, '轉發對象', 100, true)) ?? DomainError::fail('INVALID_INPUT', '請選擇有效同空間成員');
                    $recipient = $target->id;
                } else {
                    if (count($tail) < 2) {
                        DomainError::fail('INVALID_INPUT', '沒有可退回的上一手');
                    }
                    $recipient = $tail[1]->member_id;
                }
                $target = $this->todos->memberByKey($context->workspaceId, $recipient) ?? DomainError::fail('INVALID_INPUT', '上一手已停用, 請轉發給其他有效成員');
                if (! in_array('todo.writeOwn', WorkspacePermissions::resolve($target->role), true) || $recipient === $context->memberId) {
                    DomainError::fail('INVALID_INPUT', '請選擇另一位可接待辦的成員');
                }
                if ($operation === 'forwardTodo') {
                    $this->todos->addParticipant($todo, $recipient);
                    $this->todos->push($todo, $tail[0]->position + 1, $recipient);
                } else {
                    $this->todos->pop($todo, $tail[0]->position);
                }
                $this->todos->append('todo_handoffs', ['public_id' => (string) Str::uuid(), 'workspace_id' => $todo->workspace_id, 'todo_id' => $todo->id, 'from_id' => $context->memberId, 'to_id' => $recipient, 'from_name' => $actor, 'to_name' => $target->name, 'kind' => $event, 'created_at' => $at]);
                $changes = ['assignee_id' => $recipient, 'assignee_name' => $target->name, ...($operation === 'returnTodo' ? ['returned_at' => $at] : [])];
            } elseif ($operation === 'completeTodo') {
                $changes = ['state' => 'done', 'completed_at' => $at];
            }
            $this->todos->update($todo, [...$changes, 'revision' => $todo->revision + 1, 'updated_at' => $at]);
            $this->todos->audit($todo, ['event' => $event, 'actorId' => $context->userId, 'actor' => $actor, 'at' => $at->toISOString()]);
            if ($recipient !== $context->memberId) {
                $recipientMember = $this->todos->memberByKey($context->workspaceId, $recipient);
                if ($recipientMember && in_array('todo.read', WorkspacePermissions::resolve($recipientMember->role), true)) {
                    $this->todos->notify($todo, $recipient, $event, $actor);
                }
            }

            return ['id' => $todo->public_id, 'revision' => (int) $todo->revision];
        });
    }

    /** 只從伺服器身分重驗有效成員, 不信任輸入中的userId或角色
     * @param  array<string,mixed>  $identity  可信身分
     * @return PersonalReadContext 單次範圍
     */
    private function context(array $identity): PersonalReadContext
    {
        WorkspacePermissions::authorize($identity, 'workspace.read');
        WorkspacePermissions::authorize($identity, 'todo.read');
        $member = $this->todos->member($identity['workspace']['id'], $identity['user']['id']) ?? DomainError::fail('UNAUTHENTICATED', '工作空間資格已失效');

        return new PersonalReadContext($member->workspace_id, $member->id, $identity['user']['id']);
    }
}
