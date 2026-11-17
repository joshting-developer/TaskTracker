<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TokenContext;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Requests\Integrations\PublicTimeQueryRequest;
use App\Http\Requests\Integrations\PublicTimeWriteRequest;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\TimeEntryResource;
use App\Services\IssueReadService;
use App\Services\ProjectTimeReadService;
use App\Services\WorkspaceService;

/** 公開工時只適配既有業務, 不擴大作者或專案權限 */
class PublicTimeEntriesController extends Controller
{
    /** 注入Bearer邊界與既有用例
     * @param  BearerTokenSession  $tokens  鎖後Token資格與冪等邊界
     * @param  WorkspaceService  $workspaces  共用工時寫入
     * @param  ProjectTimeReadService  $time  本人工時查詢
     * @param  IssueReadService  $issues  單卡可見工時查詢
     */
    public function __construct(private BearerTokenSession $tokens, private WorkspaceService $workspaces, private ProjectTimeReadService $time, private IssueReadService $issues) {}

    /** 日期、單卡及分頁篩選只讀本人有效紀錄
     * @param  PublicTimeQueryRequest  $request  有界查詢
     * @param  string  $project  路徑Project
     * @return ApiResponse 分頁工時
     */
    public function index(PublicTimeQueryRequest $request, string $project): ApiResponse
    {
        $range = $request->toDto();

        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project, $range) {
            $page = $this->time->own($token->workspace, $project, $range, $request->validated('issueId', ''));

            return PageResource::wrap($page, TimeEntryResource::collection($page->items())->resolve());
        }, $project, 'timeEntry.readOwn'));
    }

    /** 單卡沿用網頁issue.read資格與Epic子卡彙整, 不增加跨專案報表
     * @param  PageQueryRequest  $request  分頁
     * @param  string  $project  路徑Project
     * @param  string  $issue  路徑Issue
     * @return ApiResponse 可見工時分頁
     */
    public function issue(PageQueryRequest $request, string $project, string $issue): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project, $issue) {
            $page = $this->issues->time($token->workspace, $project, $issue, $request->pagination());

            return PageResource::wrap($page, TimeEntryResource::collection($page->items())->resolve());
        }, $project));
    }

    /** 以路由決定操作, receipt之前重驗資源歸屬與本人資格
     * @param  PublicTimeWriteRequest  $request  工時寫入欄位
     * @param  string  $project  路徑Project
     * @param  ?string  $entry  路徑工時ID
     * @return ApiResponse 有界寫入結果
     */
    public function write(PublicTimeWriteRequest $request, string $project, ?string $entry = null): ApiResponse
    {
        $data = $request->toDto();
        $operation = $data->operation;
        $input = [...$data->input(), 'projectId' => $project];

        return new ApiResponse($this->tokens->writeOnce($request, 'public.v1.'.$operation, $input,
            fn (TokenContext $token) => $this->workspaces->authorizePublicTime($token->workspace->legacyIdentity(), $operation, $input),
            fn (TokenContext $token) => $this->workspaces->executePublicTime($token->workspace->legacyIdentity(), $operation, $input),
            $project, 'timeEntry.writeOwn'));
    }
}
