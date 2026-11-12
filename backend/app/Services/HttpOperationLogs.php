<?php

namespace App\Services;

use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Repositories\HttpOperationLogRepository;

/**
 * TT-260：跨資源HTTP寫入操作的事後觀測紀錄；業務歷史仍隨各自交易寫入，本紀錄不取代稽核。
 */
class HttpOperationLogs
{
    /** Bearer驗證成功後由HTTP邊界放入Request attributes的操作者快照key。 */
    public const TOKEN_ACTOR = 'tasktracker.tokenActor';

    /** 用POST呼叫但只讀取的路由；不記錄，以免輪詢淹沒紀錄。 */
    public const READ_ROUTES = [
        'rpc.getWorkspace', 'rpc.getTimeReport', 'rpc.getProjectMembers', 'rpc.getTodos', 'rpc.smtpStatus', 'rpc.getProfile',
        'auth.current', 'auth.workspaces', 'auth.invitation', 'realtime.config', 'realtime.authorize',
    ];

    /** 有效permission：可讀目前Workspace的操作紀錄。 */
    public const READ_PERMISSION = 'workspace.operationLogs.read';

    /**
     * 注入紀錄資料存取。
     *
     * @param  HttpOperationLogRepository  $logs  紀錄寫入與分頁讀取
     */
    public function __construct(private HttpOperationLogRepository $logs) {}

    /**
     * 判斷具名路由與HTTP方法是否屬於要記錄的寫入操作。
     *
     * @param  string|null  $routeName  路由名稱；未命名不記錄
     * @param  string  $method  HTTP方法
     * @return bool 是否記錄
     */
    public function shouldRecord(?string $routeName, string $method): bool
    {
        return $routeName !== null && ! in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) && ! in_array($routeName, self::READ_ROUTES, true);
    }

    /**
     * 追加一筆操作紀錄；只收識別碼、狀態與耗時，不收內容或秘密。
     *
     * @param  array{workspaceId:?string,userId:?string,tokenId:?string,routeName:string,method:string,status:int,projectId:?string,requestId:?string,durationMs:int}  $entry  已由HTTP邊界整理的欄位
     * @return void
     */
    public function record(array $entry): void
    {
        $this->logs->append([
            'workspace_public_id' => $entry['workspaceId'], 'user_id' => $entry['userId'], 'token_id' => $entry['tokenId'],
            'route_name' => substr($entry['routeName'], 0, 80), 'method' => $entry['method'], 'status' => $entry['status'],
            'project_public_id' => $entry['projectId'], 'request_id' => $entry['requestId'],
            'duration_ms' => max(0, $entry['durationMs']), 'occurred_at' => now()->utc()->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * 目前Workspace的操作紀錄，需有效permission；以before游標倒序分頁。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分與有效權限
     * @param  int  $limit  每頁筆數（1–100）
     * @param  int|null  $before  上一頁回傳的nextBefore
     * @return array{items:array<int,array<string,mixed>>,limit:int,nextBefore:string|null} 有界紀錄
     *
     * @throws DomainError 沒有讀取權限
     */
    public function listing(WorkspaceContext $context, int $limit, ?int $before): array
    {
        if (! in_array(self::READ_PERMISSION, $context->permissions, true)) {
            DomainError::fail('FORBIDDEN', '沒有讀取操作紀錄的權限');
        }
        $rows = $this->logs->page($context->workspaceId, $limit, $before);
        $more = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return ['items' => $rows->map(fn ($row) => [
            'id' => (string) $row->id, 'routeName' => $row->route_name, 'method' => $row->method, 'status' => $row->status,
            'userId' => $row->user_id, 'userName' => $row->user_name, 'tokenId' => $row->token_id, 'projectId' => $row->project_public_id,
            'requestId' => $row->request_id, 'durationMs' => $row->duration_ms, 'occurredAt' => $row->occurred_at->utc()->toISOString(),
        ])->values()->all(), 'limit' => $limit, 'nextBefore' => $more ? (string) $rows->last()->id : null];
    }
}
