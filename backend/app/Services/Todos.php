<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspacePermissions;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Domain\TodoContent;
use App\Repositories\RecordStore;
use Illuminate\Support\Str;

// 與 Project Issue 分離；由 Controller 交易包含資格、版本、寫入及通知
/**
 * 管理個人及交辦待辦，依參與者授權並原子轉成正式卡片。
 */
class Todos
{
    public const OPERATIONS = ['getTodos', 'createTodo', 'updateTodo', 'completeTodo', 'reopenTodo', 'returnTodo', 'cancelTodo', 'convertTodo', 'readTodoNotification', 'commentTodo', 'forwardTodo'];

    private string $userId;

    /**
     * 綁定本次操作的可信範圍與協作者；由TodosFactory在交易內以重驗的Session建立。
     *
     * @param  array  $workspaceSession  從後端Session重新驗證的工作空間身分與權限
     * @param  RecordStore  $store  已限定目前Workspace的資料入口
     * @param  ProjectAccess  $access  轉卡與轉卡連結的Project權限檢查
     * @param  RequestReplay  $replay  防重複提交與原結果重播
     * @param  WorkspaceService  $workspaces  轉成正式卡片時沿用的RPC寫入入口
     * @param  TodoCollaborationService  $collaboration  留言與交接用例
     */
    public function __construct(private array $workspaceSession, private RecordStore $store, private ProjectAccess $access, private RequestReplay $replay, private WorkspaceService $workspaces, private TodoCollaborationService $collaboration)
    {
        $this->userId = $workspaceSession['user']['id'];
    }

    /**
     * 驗證操作邊界並執行查詢或受保護的寫入。
     *
     * @param  string  $operation  已允許的操作名稱
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function execute(string $operation, array $input): array
    {
        if (! in_array($operation, self::OPERATIONS, true)) {
            DomainError::fail('NOT_FOUND', '找不到這個操作');
        }
        WorkspacePermissions::authorize($this->workspaceSession, 'workspace.read');
        WorkspacePermissions::authorize($this->workspaceSession, 'todo.read');
        $member = $this->store->get('members', $this->userId);
        if (! $member || ! empty($member['disabledAt'])) {
            DomainError::fail('UNAUTHENTICATED', '工作空間資格已失效');
        }
        if ($operation === 'getTodos') {
            return $this->listing();
        }
        if (in_array($operation, TodoCollaborationService::OPERATIONS, true)) {
            return $this->collaboration->execute($this->workspaceSession, $this->store, $operation, $input);
        }
        $todo = null;
        $notification = null;
        if ($operation === 'readTodoNotification') {
            $notification = $this->store->require('todoNotifications', Fields::text($input['id'] ?? null, '通知', 100, true));
            if ($notification['recipientId'] !== $this->userId) {
                DomainError::fail('NOT_FOUND', '找不到這筆通知');
            }
            $todo = $this->readable($notification['todoId']);
        } else {
            WorkspacePermissions::authorize($this->workspaceSession, 'todo.writeOwn');
            if ($operation === 'createTodo') {
                $this->assignee($input['assigneeId'] ?? $this->userId);
            } else {
                $todo = $this->readable(Fields::text($input['id'] ?? null, '待辦', 100, true));
                $actorField = in_array($operation, ['updateTodo', 'cancelTodo'], true) ? 'createdBy' : 'assigneeId';
                if ($todo[$actorField] !== $this->userId) {
                    DomainError::fail('FORBIDDEN', '沒有執行此待辦操作的權限');
                }
                if ($operation === 'returnTodo' && $todo['createdBy'] === $this->userId) {
                    DomainError::fail('FORBIDDEN', '自己的待辦不需要退回');
                }
                if ($operation === 'convertTodo') {
                    $this->access->require(Fields::text($input['issue']['projectId'] ?? null, '專案', 100, true), 'issue.write');
                }
            }
        }

        // 失去資格／退回後, 舊 requestId 也先重新檢查, 不回傳內容快照
        return $this->replay->execute($this->store, 'todoRequests', $this->userId, $operation, $input, function () use ($operation, $input, $todo, $notification) {
            if ($operation === 'createTodo') {
                $result = $this->create($input);
            } elseif ($operation === 'readTodoNotification') {
                $this->store->put('todoNotifications', $notification['id'], [
                    ...$notification,
                    'readAt' => $notification['readAt'] ?? $this->now(),
                ]);
                $result = ['id' => $notification['id']];
            } else {
                $result = $this->change($operation, $input, $todo);
            }

            return $result;
        });
    }

    /**
     * 取得目前UTC ISO時間供紀錄與歷史使用。
     *
     * @return string
     */
    private function now(): string
    {
        return now()->utc()->toISOString();
    }

    /**
     * 判斷操作者是否為待辦交辦者或目前承辦者。
     *
     * @param  array  $todo  目前待辦紀錄
     * @return bool
     */
    private function participant(array $todo): bool
    {
        return in_array($this->userId, [$todo['createdBy'], $todo['assigneeId']], true);
    }

    /**
     * 取得目前操作者可以讀取的待辦。
     *
     * @param  string  $id  紀錄的固定識別碼
     * @return array
     */
    private function readable(string $id): array
    {
        $todo = $this->store->get('todos', $id);
        if (! $todo || (! $this->participant($todo) && ! $this->collaboration->canRead($this->workspaceSession, $id))) {
            DomainError::fail('NOT_FOUND', '找不到或無權存取這筆待辦');
        }

        return $todo;
    }

    /**
     * 驗證同空間承辦者與交辦權限。
     *
     * @param  mixed  $value  待驗證或正規化的值
     * @return string
     */
    private function assignee(mixed $value): string
    {
        $id = Fields::text($value, '承辦者', 100, true);
        if ($id !== $this->userId) {
            WorkspacePermissions::authorize($this->workspaceSession, 'todo.assign');
        }
        $member = $this->store->get('members', $id);
        if (! $member || ! empty($member['disabledAt']) || ! in_array('todo.writeOwn', WorkspacePermissions::resolve($member['role'] ?? ''), true)) {
            DomainError::fail('INVALID_INPUT', '請選擇有效的同空間成員');
        }

        return $id;
    }

    /**
     * 整理本人參與的待辦、通知與逐筆操作能力。
     *
     * @return array
     */
    private function listing(): array
    {
        $todos = array_values(array_filter($this->store->all('todos'), fn ($todo) => $this->participant($todo)));
        usort($todos, fn ($a, $b) => strcmp($b['updatedAt'], $a['updatedAt']) ?: strcmp($a['id'], $b['id']));
        $byId = array_column($todos, null, 'id');
        $notifications = [];
        foreach ($this->store->all('todoNotifications') as $notification) {
            if ($notification['recipientId'] !== $this->userId || ! isset($byId[$notification['todoId']])) {
                continue;
            }
            $notifications[] = [
                ...$notification,
                'title' => $byId[$notification['todoId']]['title'],
            ];
        }
        usort($notifications, fn ($a, $b) => strcmp($b['createdAt'], $a['createdAt']) ?: strcmp($a['id'], $b['id']));
        $canAssign = in_array('todo.assign', $this->workspaceSession['permissions'], true);
        $eligibleMembers = array_filter($this->store->all('members'), function ($member) use ($canAssign) {
            $isActive = empty($member['disabledAt']);
            $canChooseMember = $canAssign || $member['id'] === $this->userId;
            $canReceiveTodo = in_array('todo.writeOwn', WorkspacePermissions::resolve($member['role'] ?? ''), true);

            return $isActive && $canChooseMember && $canReceiveTodo;
        });
        $members = array_values(array_map(fn ($member) => [
            'id' => $member['id'],
            'name' => $member['name'],
            'email' => $member['email'],
        ], $eligibleMembers));
        $write = in_array('todo.writeOwn', $this->workspaceSession['permissions'], true);
        $access = $this->access;
        $todos = array_map(function ($t) use ($write, $access) {
            $link = null;
            if (! empty($t['convertedIssueId']) && $access->can($t['convertedProjectId'], 'issue.read')) {
                $issue = $this->store->get('issues', $t['convertedIssueId']);
                if ($issue) {
                    $link = [
                        'id' => $issue['id'],
                        'projectId' => $issue['projectId'],
                        'identifier' => $issue['identifier'],
                    ];
                }
            }
            unset($t['convertedIssueId'], $t['convertedProjectId']);

            return [
                ...$t,
                'convertedIssue' => $link,
                'capabilities' => [
                    'edit' => $write && $t['createdBy'] === $this->userId && $t['state'] === 'open',
                    'cancel' => $write && $t['createdBy'] === $this->userId && $t['state'] === 'open',
                    'complete' => $write && $t['assigneeId'] === $this->userId && $t['state'] === 'open',
                    'reopen' => $write && $t['assigneeId'] === $this->userId && $t['state'] === 'done',
                    'return' => $write && $t['assigneeId'] === $this->userId && $t['createdBy'] !== $this->userId && $t['state'] === 'open',
                    'convert' => $write && $t['assigneeId'] === $this->userId && $t['state'] === 'open',
                ],
            ];
        }, $todos);

        return [
            'todos' => $todos,
            'notifications' => $notifications,
            'members' => $members,
        ];
    }

    /**
     * 建立個人或交辦待辦並產生必要通知。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    private function create(array $input): array
    {
        $id = (string) Str::uuid();
        $at = $this->now();
        $assigneeId = $this->assignee($input['assigneeId'] ?? $this->userId);
        $todo = [
            'id' => $id,
            ...TodoContent::fields($input),
            'createdBy' => $this->userId,
            'creatorName' => $this->workspaceSession['user']['name'],
            'assigneeId' => $assigneeId,
            'initialAssigneeId' => $assigneeId,
            'assigneeName' => $this->store->require('members', $assigneeId)['name'],
            'state' => 'open',
            'revision' => 1,
            'createdAt' => $at,
            'updatedAt' => $at,
            'completedAt' => null,
            'returnedAt' => null,
            'history' => [[
                'event' => 'created',
                'actorId' => $this->userId,
                'actor' => $this->workspaceSession['user']['name'],
                'at' => $at,
            ]],
        ];
        $this->store->put('todos', $id, $todo);
        $this->collaboration->initialize($this->workspaceSession, $id);
        $this->notify($todo, $assigneeId, 'assigned');

        return [
            'id' => $id,
            'revision' => 1,
        ];
    }

    /**
     * 依版本與狀態更新待辦，轉卡與狀態變動保持原子性。
     *
     * @param  string  $operation  已允許的操作名稱
     * @param  array  $input  此次操作的原始輸入欄位
     * @param  array  $todo  目前待辦紀錄
     * @return array
     */
    private function change(string $operation, array $input, array $todo): array
    {
        if (($input['expectedRevision'] ?? null) !== $todo['revision']) {
            DomainError::fail('REVISION_CONFLICT', '待辦已被更新, 請重新載入後再修改');
        }
        $requiredState = $operation === 'reopenTodo' ? 'done' : 'open';
        if ($todo['state'] !== $requiredState) {
            DomainError::fail('INVALID_INPUT', '待辦狀態已改變, 請重新整理');
        }
        $at = $this->now();
        if ($operation === 'convertTodo') {
            $fields = $input['issue'] ?? null;
            if (! is_array($fields) || array_is_list($fields)) {
                DomainError::fail('INVALID_INPUT', '請填寫正式卡片資料');
            }
            // 與待辦狀態同交易, 失敗不留半張卡；衍生 requestId 不與業務操作共用
            $issue = $this->workspaces->execute($this->workspaceSession, 'createIssue', [
                'title' => $todo['title'],
                'description' => $todo['content'] ?? $todo['note'],
                ...$fields,
                'requestId' => 'todo-convert:'.hash('sha256', $todo['id'].':'.$input['requestId']),
            ]);
        }
        $event = match ($operation) {
            'updateTodo' => 'edited',
            'completeTodo' => 'completed',
            'reopenTodo' => 'reopened',
            'returnTodo' => 'returned',
            'cancelTodo' => 'canceled',
            'convertTodo' => 'converted',
        };
        $changes = match ($operation) {
            'updateTodo' => TodoContent::fields($input, $todo),
            'completeTodo' => [
                'state' => 'done',
                'completedAt' => $at,
            ],
            'reopenTodo' => [
                'state' => 'open',
                'completedAt' => null,
            ],
            'returnTodo' => [
                'assigneeId' => $todo['createdBy'],
                'assigneeName' => $todo['creatorName'],
                'returnedAt' => $at,
            ],
            'cancelTodo' => ['state' => 'canceled'],
            'convertTodo' => [
                'state' => 'converted',
                'convertedIssueId' => $issue['id'],
                'convertedProjectId' => $issue['projectId'],
            ],
        };
        $updated = [
            ...$todo,
            ...$changes,
            'revision' => $todo['revision'] + 1,
            'updatedAt' => $at,
            'history' => [
                ...$todo['history'],
                [
                    'event' => $event,
                    'actorId' => $this->userId,
                    'actor' => $this->workspaceSession['user']['name'],
                    'at' => $at,
                ],
            ],
        ];
        $this->store->put('todos', $todo['id'], $updated);
        $recipient = $todo['createdBy'] === $this->userId ? $todo['assigneeId'] : $todo['createdBy'];
        $this->notify($updated, $recipient, $event);

        return [
            'id' => $updated['id'],
            'revision' => $updated['revision'],
        ];
    }

    /**
     * 對有效的另一位參與者建立待辦通知。
     *
     * @param  array  $todo  目前待辦紀錄
     * @param  string  $recipientId  通知收件者識別碼
     * @param  string  $event  待辦異動事件
     * @return void
     */
    private function notify(array $todo, string $recipientId, string $event): void
    {
        $member = $this->store->get('members', $recipientId);
        if ($recipientId === $this->userId || ! $member || ! empty($member['disabledAt']) || ! in_array('todo.read', WorkspacePermissions::resolve($member['role'] ?? ''), true)) {
            return;
        }
        $id = (string) Str::uuid();
        $this->store->put('todoNotifications', $id, [
            'id' => $id,
            'todoId' => $todo['id'],
            'recipientId' => $recipientId,
            'event' => $event,
            'actor' => $this->workspaceSession['user']['name'],
            'createdAt' => $this->now(),
            'readAt' => null,
        ]);
    }
}
