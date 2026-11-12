<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageQueryRequest;
use App\Http\Requests\ProjectLeadQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\ProjectResource;
use App\Http\WorkspaceTransaction;
use App\Services\ProjectReadService;
use Illuminate\Http\Request;

/** 專案讀取HTTP邊界，與相容RPC寫入分開。 */
class ProjectsController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceTransaction  $workspace  此Controller的穩定協作者
     * @param  ProjectReadService  $projects  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceTransaction $workspace, private ProjectReadService $projects) {}

    /**
     * 取得當次已授權且有界的列表，不附帶其他頁面的資料。
     *
     * @param  PageQueryRequest  $request  搜尋與分頁條件
     * @return ApiResponse 當頁專案摘要及有效能力
     */
    public function index(PageQueryRequest $request): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['result' => $this->projects->index($context, $request->pagination())]);
        $result = $data['result'];
        $items = array_map(fn ($p) => [...(new ProjectResource($p))->resolve(), 'permissions' => $result->permissions[$p->public_id]], $result->page->items());

        return new ApiResponse(PageResource::wrap($result->page, $items));
    }

    /**
     * 取得已授權建立／編輯Project用途的負責人候選。
     *
     * @param  ProjectLeadQueryRequest  $request  用途與分頁條件
     * @return ApiResponse 最小成員選項分頁
     */
    public function leads(ProjectLeadQueryRequest $request): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->projects->leadOptions($context, $request->pagination(), $request->projectId())]);

        return new ApiResponse(PageResource::wrap($data['page'], array_map(fn ($member) => ['id' => $member->id, 'name' => $member->name], $data['page']->items())));
    }

    /**
     * 提供可轉卡Project最小候選與總數，不讀其他頁面資料。
     *
     * @param  PageQueryRequest  $request  有界分頁／搜尋
     * @return ApiResponse 最小候選分頁
     */
    public function options(PageQueryRequest $request): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->projects->options($context, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], array_map(fn ($project) => ['id' => $project->public_id, 'name' => $project->name, 'code' => $project->code], $data['page']->items())));
    }

    /**
     * 只取得指定專案的屬性及狀態，保留找不到／無權限語意。
     *
     * @param  Request  $request  當次HTTP請求
     * @param  string  $project  專案公開識別碼
     * @return ApiResponse 專案屬性與有效能力
     */
    public function show(Request $request, string $project): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => $this->projects->show($context, $project));

        return new ApiResponse([...(new ProjectResource($data['project']))->resolve(), ...$data['capabilities']]);
    }

    /**
     * 僅提供已授權Project的成員姓名分頁，不載完整ACL。
     *
     * @param  PageQueryRequest  $request  分頁條件
     * @param  string  $project  Project公開ID
     * @return ApiResponse 最小成員選項
     */
    public function members(PageQueryRequest $request, string $project): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->projects->memberOptions($context, $project, $request->pagination())]);
        $items = array_map(fn ($m) => ['id' => $m->user_id, 'name' => $m->getRelation('user')->name], $data['page']->items());

        return new ApiResponse(PageResource::wrap($data['page'], $items));
    }

    /**
     * 只提供狀態使用數，不帶卡片清單。
     *
     * @param  Request  $request  HTTP請求
     * @param  string  $project  專案公開ID
     * @return ApiResponse 狀態使用摘要
     */
    public function statusUsage(Request $request, string $project): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->projects->statusUsage($context, $project)));
    }
}
