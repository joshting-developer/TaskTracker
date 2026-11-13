<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TokenContext;
use App\Domain\DomainError;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\MyNotificationResource;
use App\Services\ProjectNotificationReadService;
use Illuminate\Http\Request;

/**
 * TT-315：讓Agent自己用PAT讀寄給自己的任務通知（指派、留言、狀態調整…）, 不用另外去「通知」Agent。
 * 沿用網頁的通知資料與專案權限, 外層只加Token範圍。
 */
class PublicNotificationsController extends Controller
{
    /**
     * 注入Token驗證與通知查詢。
     *
     * @param  BearerTokenSession  $tokens  Token驗證、交易與稽核
     * @param  ProjectNotificationReadService  $notifications  通知查詢
     */
    public function __construct(private BearerTokenSession $tokens, private ProjectNotificationReadService $notifications) {}

    /**
     * 我的通知：after＝上次回傳的nextAfter, limit 1–100（預設50）, 依位置遞增。
     *
     * @param  Request  $request  查詢參數
     * @return ApiResponse {items, nextAfter, hasMore}
     */
    public function index(Request $request): ApiResponse
    {
        $limit = filter_var($request->query('limit', 50), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        $after = $request->query('after');
        $after = $after === null ? null : filter_var($after, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limit === false || $after === false) {
            DomainError::fail('INVALID_INPUT', '請確認查詢範圍');
        }

        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($after, $limit) {
            $result = $this->notifications->mine($token->workspace, $after, $limit);
            $result['items'] = MyNotificationResource::collection($result['items'])->resolve();

            return $result;
        }));
    }
}
