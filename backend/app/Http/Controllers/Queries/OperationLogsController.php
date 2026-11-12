<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceTransaction;
use App\Services\HttpOperationLogs;
use Illuminate\Http\Request;

/** TT-260：目前Workspace的HTTP操作紀錄查詢，只回識別碼、狀態與耗時。 */
class OperationLogsController extends Controller
{
    /**
     * 注入讀取交易邊界與紀錄服務。
     *
     * @param  WorkspaceTransaction  $transactions  Session重驗與唯讀交易
     * @param  HttpOperationLogs  $logs  權限檢查與分頁
     */
    public function __construct(private WorkspaceTransaction $transactions, private HttpOperationLogs $logs) {}

    /**
     * 依before游標倒序分頁，limit預設50、上限100。
     *
     * @param  Request  $request  Session身分與limit／before查詢參數
     * @return ApiResponse 有界紀錄與下一頁游標
     *
     * @throws DomainError 參數無效、未登入或沒有權限
     */
    public function index(Request $request): ApiResponse
    {
        $limit = filter_var($request->query('limit', 50), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        $before = $request->query('before');
        $before = $before === null ? null : filter_var($before, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limit === false || $before === false) {
            DomainError::fail('INVALID_INPUT', '分頁參數無效');
        }

        return new ApiResponse($this->transactions->read($request, fn (WorkspaceContext $context) => $this->logs->listing($context, $limit, $before)));
    }
}
