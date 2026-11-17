<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TokenContext;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Requests\BoardPageQueryRequest;
use App\Http\Requests\BoardSelectionRequest;
use App\Http\Requests\Integrations\PublicBoardWriteRequest;
use App\Http\Requests\IssueQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\BoardPreviewResource;
use App\Http\Resources\Queries\BoardSummaryResource;
use App\Http\Resources\Queries\IssueListPageResource;
use App\Http\Resources\Queries\IssueListResource;
use App\Http\Resources\Queries\PageResource;
use App\Services\BoardReadService;
use App\Services\BoardSelectionService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;

/** 公開看板查詢沿用網頁的同Project授權、投影與分頁 */
class PublicBoardsController extends Controller
{
    /**
     * 注入Bearer資格邊界與看板讀取用例, 不在建構時查資料
     *
     * @param  BearerTokenSession  $tokens  每次重驗PAT能力與資格
     * @param  BoardSelectionService  $selections  有界條件預覽
     * @param  BoardReadService  $boards  同Project看板用例
     * @param  WorkspaceService  $workspaces  共用看板寫入與重播前授權
     */
    public function __construct(private BearerTokenSession $tokens, private BoardReadService $boards, private WorkspaceService $workspaces, private BoardSelectionService $selections) {}

    /**
     * 取得看板摘要分頁及虛擬Default, 不附收錄ID或卡片內容
     *
     * @param  BoardPageQueryRequest  $request  搜尋、封存與有界分頁
     * @param  string  $project  Project公開ID
     * @return ApiResponse 有界摘要與Default
     */
    public function index(BoardPageQueryRequest $request, string $project): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project) {
            $data = $this->boards->index($token->workspace, $project, $request->pagination(), $request->archived());
            $items = array_map(fn ($board) => (new BoardSummaryResource($board, $project))->resolve(), $data['page']->items());

            return [...PageResource::wrap($data['page'], $items), 'defaultBoard' => $data['defaultBoard']];
        }));
    }

    /**
     * 讀取單一看板及最多1000個明確收錄ID, 已封存仍可讀
     *
     * @param  Request  $request  純Bearer請求
     * @param  string  $project  Project公開ID
     * @param  string  $board  看板公開ID或default
     * @return ApiResponse 看板屬性與有限收錄
     */
    public function show(Request $request, string $project, string $board): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, fn (TokenContext $token) => $this->boards->show($token->workspace, $project, $board)));
    }

    /**
     * 看板範圍與Epic展開在SQL執行, 回公開卡片摘要分頁
     *
     * @param  IssueQueryRequest  $request  卡片篩選與分頁
     * @param  string  $project  Project公開ID
     * @param  string  $board  看板公開ID或default
     * @return ApiResponse 不含原稿或活動的卡片分頁
     */
    public function issues(IssueQueryRequest $request, string $project, string $board): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project, $board) {
            $page = $this->boards->issues($token->workspace, $project, $board, $request->toDto());

            return IssueListPageResource::wrap($page, IssueListResource::collection($page->items())->resolve());
        }));
    }

    /**
     * 建立自訂看板或按版本套用差量, 不能改Default或公開封存
     *
     * @param  PublicBoardWriteRequest  $request  有界且拒絕未知欄位的JSON
     * @param  string  $project  路徑Project公開ID
     * @param  string|null  $board  更新目標, 建立時為null
     * @return ApiResponse 固定ID與版本投影
     */
    public function write(PublicBoardWriteRequest $request, string $project, ?string $board = null): ApiResponse
    {
        $input = $request->toDto($project, $board)->input();

        return new ApiResponse($this->tokens->writeOnce($request, $board !== null ? 'public.v1.patchBoard' : 'public.v1.createBoard', $input,
            fn (TokenContext $token) => $this->workspaces->authorizePublicBoard($token->workspace->legacyIdentity(), $input),
            fn (TokenContext $token) => $this->workspaces->executePublicBoard($token->workspace->legacyIdentity(), $input),
            $project, 'board.manage'));
    }

    /** 預覽未儲存條件的數量與首 20 張卡片, 不修改任何資料
     * @param  BoardSelectionRequest  $request  有界且型別化的條件
     * @param  string  $project  Project 公開 ID
     * @return ApiResponse 預覽摘要與總數
     */
    public function preview(BoardSelectionRequest $request, string $project): ApiResponse
    {
        $page = $this->tokens->read($request, fn (TokenContext $token) => $this->selections->preview($token->workspace, $project, $request->toDto()), $project, 'board.manage');

        return new ApiResponse(PageResource::wrap($page, BoardPreviewResource::collection($page->items())->resolve()));
    }
}
