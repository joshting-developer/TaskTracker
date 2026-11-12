<?php

namespace App\Http\Controllers;

use App\Http\Requests\Writes\IssueWriteRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceRpc;

/**
 * 提供任務建立、更新、留言及刪除HTTP入口。
 */
class IssueController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceRpc  $rpc  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceRpc $rpc) {}

    /**
     * 驗證JSON後建立任務及必要關聯，回傳既有API格式。
     *
     * @param  IssueWriteRequest  $request  已檢查型別的寫入輸入
     * @return ApiResponse
     */
    public function store(IssueWriteRequest $request): ApiResponse
    {
        $result = $this->rpc->execute($request, 'createIssue', $request->toDto()->input());

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後更新任務內容與關聯，回傳既有API格式。
     *
     * @param  IssueWriteRequest  $request  已檢查型別的寫入輸入
     * @return ApiResponse
     */
    public function update(IssueWriteRequest $request): ApiResponse
    {
        $result = $this->rpc->execute($request, 'updateIssue', $request->toDto()->input());

        return new ApiResponse($result);
    }

    /**
     * 驗證JSON後新增任務留言，回傳既有API格式。
     *
     * @param  IssueWriteRequest  $request  已檢查型別的寫入輸入
     * @return ApiResponse
     */
    public function comment(IssueWriteRequest $request): ApiResponse
    {
        $result = $this->rpc->execute($request, 'addComment', $request->toDto()->input());

        return new ApiResponse($result);
    }

    /**
     * TT-250：依版本永久刪除卡片，回傳刪除前版本＋1的有界結果。
     *
     * @param  IssueWriteRequest  $request  已檢查型別的id、expectedRevision與requestId
     * @return ApiResponse
     */
    public function destroy(IssueWriteRequest $request): ApiResponse
    {
        return new ApiResponse($this->rpc->execute($request, 'deleteIssue', $request->toDto()->input()));
    }
}
