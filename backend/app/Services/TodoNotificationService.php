<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Data\PersonalReadContext;
use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Factories\RecordStoreFactory;
use App\Repositories\TodoReadRepository;

/** 通知已讀用例, 由HTTP交易鎖與當次資格限制寫入範圍 */
class TodoNotificationService
{
    /** 注入資料入口與既有防重播協作者
     * @param  TodoReadRepository  $todos  個人可見通知範圍
     * @param  RecordStoreFactory  $records  交易內組裝receipt資料入口
     * @param  RequestReplay  $replay  已授權請求的防重播
     */
    public function __construct(private TodoReadRepository $todos, private RecordStoreFactory $records, private RequestReplay $replay) {}

    /** 將目前仍可讀的本人通知一次標為已讀, 重試不影響後來的新通知
     * @param  WorkspaceContext  $context  交易內重驗的身分與工作區
     * @param  array<string,mixed>  $input  原始requestId簽章
     * @return array{updated:int} 本次修改筆數, 重播回首次結果
     *
     * @throws DomainError 資格失效或requestId衝突
     */
    public function readAll(WorkspaceContext $context, array $input): array
    {
        WorkspacePermissions::authorize($context->legacyIdentity(), 'todo.read');
        $member = $this->todos->member($context->workspaceId, $context->userId) ?? DomainError::fail('UNAUTHENTICATED', '工作空間資格已失效');
        $personal = new PersonalReadContext($member->workspace_id, $member->id, $context->userId, (int) $member->role_revision);

        return $this->replay->execute($this->records->forWorkspace($context->workspaceId), 'todoRequests', $context->userId, 'readAllTodoNotifications', $input,
            fn () => ['updated' => $this->todos->markNotificationsRead($personal, now()->utc()->toISOString())]);
    }
}
