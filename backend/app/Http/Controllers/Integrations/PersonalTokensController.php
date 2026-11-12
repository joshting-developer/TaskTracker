<?php

namespace App\Http\Controllers\Integrations;

use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\CreateTokenRequest;
use App\Http\Resources\ApiResponse;
use App\Http\WorkspaceTransaction;
use App\Services\Integrations\PersonalTokens;
use Illuminate\Http\Request;

/** 僅Session／CSRF可管理本人Token；不讓Bearer建立更多憑證。 */
class PersonalTokensController extends Controller
{
    /** 注入共用交易／Session重驗邊界與個人Token用例，不在Controller查DB。 */
    public function __construct(private WorkspaceTransaction $transactions, private PersonalTokens $tokens) {}

    /**
     * 只讀本人目前工作區Token metadata，不讀取原文／雜湊。
     *
     * @param  Request  $request  Session身分與頁碼
     * @return ApiResponse 每頁20筆的安全清單
     */
    public function index(Request $request): ApiResponse
    {
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);
        if ($page === false || $page < 1 || $page > 1000000) {
            DomainError::fail('INVALID_INPUT', '頁碼無效');
        }

        return new ApiResponse($this->transactions->read($request, fn (WorkspaceContext $context) => $this->tokens->listing($context, $page)));
    }

    /**
     * 在共用寫入交易內建立一次顯示的PAT。
     *
     * @param  CreateTokenRequest  $request  已驗證Token欄位
     * @return ApiResponse metadata及一次性原文
     */
    public function store(CreateTokenRequest $request): ApiResponse
    {
        return new ApiResponse($this->transactions->write($request, fn (WorkspaceContext $context) => $this->tokens->create($context, $request->validated())));
    }

    /**
     * 撤銷本人目前工作區Token，不刪除管理紀錄。
     *
     * @param  Request  $request  Session與CSRF請求
     * @param  string  $token  Token公開管理ID
     * @return ApiResponse 撤銷後metadata
     */
    public function revoke(Request $request, string $token): ApiResponse
    {
        if (! ctype_digit($token)) {
            DomainError::fail('NOT_FOUND', '找不到Token');
        }

        return new ApiResponse($this->transactions->write($request, fn (WorkspaceContext $context) => $this->tokens->revoke($context, $token)));
    }
}
