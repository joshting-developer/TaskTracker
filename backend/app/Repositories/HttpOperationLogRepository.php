<?php

namespace App\Repositories;

use App\Models\HttpOperationLog;
use Illuminate\Support\Collection;

/** TT-260：HTTP操作紀錄的寫入與限定Workspace的keyset分頁讀取。 */
class HttpOperationLogRepository
{
    /**
     * 追加一筆已整理好的紀錄；呼叫端負責欄位長度與不含敏感內容。
     *
     * @param  array{workspace_public_id:?string,user_id:?string,token_id:?string,route_name:string,method:string,status:int,project_public_id:?string,request_id:?string,duration_ms:int,occurred_at:string}  $row  紀錄欄位
     * @return void
     */
    public function append(array $row): void
    {
        HttpOperationLog::insert($row);
    }

    /**
     * 依id倒序讀取同Workspace紀錄，以before游標避免深OFFSET與COUNT成長；多取一筆判斷是否還有下一頁。
     *
     * @param  string  $workspaceId  可信Workspace公開ID
     * @param  int  $limit  已驗證的每頁筆數
     * @param  int|null  $before  只取id小於此值的紀錄
     * @return Collection<int,HttpOperationLog> 最多limit+1筆，含操作者名稱
     */
    public function page(string $workspaceId, int $limit, ?int $before): Collection
    {
        return HttpOperationLog::query()->leftJoin('users', 'users.id', '=', 'http_operation_logs.user_id')
            ->where('http_operation_logs.workspace_public_id', $workspaceId)
            ->when($before !== null, fn ($q) => $q->where('http_operation_logs.id', '<', $before))
            ->orderByDesc('http_operation_logs.id')->limit($limit + 1)
            ->get(['http_operation_logs.id', 'http_operation_logs.user_id', 'users.name as user_name', 'http_operation_logs.token_id',
                'http_operation_logs.route_name', 'http_operation_logs.method', 'http_operation_logs.status', 'http_operation_logs.project_public_id',
                'http_operation_logs.request_id', 'http_operation_logs.duration_ms', 'http_operation_logs.occurred_at']);
    }
}
