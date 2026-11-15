<?php

namespace App\Http\Controllers;

use App\Data\WorkspaceContext;
use App\Http\Requests\PageQueryRequest;
use App\Http\Requests\TagWriteRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\TagResource;
use App\Http\WorkspaceTransaction;
use App\Services\TagService;
use Illuminate\Http\Request;

/** Session 標籤 HTTP 適配, 不直接讀 Model 或 Repository */
class TagsController extends Controller
{
    /** 注入 Session 交易邊界與標籤服務
     *
     * @param  WorkspaceTransaction  $workspace  當次身分與交易
     * @param  TagService  $tags  共用標籤用例
     */
    public function __construct(private WorkspaceTransaction $workspace, private TagService $tags) {}

    /** 只回當頁的專案標籤定義
     *
     * @param  PageQueryRequest  $request  有界分頁
     * @param  string  $project  專案公開 ID
     * @return ApiResponse 標籤定義分頁
     */
    public function index(PageQueryRequest $request, string $project): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, function (WorkspaceContext $context) use ($request, $project) {
            $page = $this->tags->index($context, $project, $request->pagination());

            return PageResource::wrap($page, TagResource::collection($page->items())->resolve());
        }));
    }

    /** 讀取已授權專案的單一定義
     *
     * @param  Request  $request  Session 資格
     * @param  string  $project  專案公開 ID
     * @param  string  $tag  定義公開 ID
     * @return ApiResponse 單一定義
     */
    public function show(Request $request, string $project, string $tag): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => (new TagResource($this->tags->show($context, $project, $tag)))->resolve()));
    }

    /** 以已驗 DTO 進入同交易標籤用例
     *
     * @param  TagWriteRequest  $request  有界寫入
     * @param  string  $project  專案公開 ID
     * @return ApiResponse 有界寫入結果
     */
    public function write(TagWriteRequest $request, string $project): ApiResponse
    {
        $data = $request->toDto();
        $target = $request->route('tag') ?? $request->route('issue');

        return new ApiResponse($this->workspace->write($request, fn (WorkspaceContext $context) => $this->tags->sessionWrite($context, $project, $data->action, $target, $data->input)));
    }
}
