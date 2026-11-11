<?php

namespace App\Http\Controllers\Queries;

use App\Http\ApiExecution;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\ProjectResource;
use App\Services\ProjectReadService;
use Illuminate\Http\Request;

/** 專案讀取HTTP邊界，與相容RPC寫入分開。 */
class ProjectsController extends Controller
{
    /** 取得當次已授權且有界的列表，不附帶其他頁面的資料。 */
    public function index(PageQueryRequest $request, ApiExecution $execution, ProjectReadService $projects): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['result' => $projects->index($actor, $request->pagination())]);
        $result = $data['result'];
        $items = array_map(fn ($p) => [...(new ProjectResource($p))->resolve(), 'permissions' => $result->permissions[$p->public_id]], $result->page->items());

        return new ApiResponse(PageResource::wrap($result->page, $items));
    }

    /** 只取得指定資源的資料，保留找不到／無權限語意。 */
    public function show(Request $request, string $project, ApiExecution $execution, ProjectReadService $projects): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => $projects->show($actor, $project));

        return new ApiResponse([...(new ProjectResource($data['project']))->resolve(), ...$data['capabilities']]);
    }

    /**
     * 僅提供已授權Project的成員姓名分頁，不載完整ACL。
     *
     * @param  PageQueryRequest  $request  分頁條件
     * @param  string  $project  Project公開ID
     * @param  ApiExecution  $execution  HTTP資格邊界
     * @param  ProjectReadService  $projects  Project用例
     * @return ApiResponse 最小成員選項
     */
    public function members(PageQueryRequest $request, string $project, ApiExecution $execution, ProjectReadService $projects): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['page' => $projects->memberOptions($actor, $project, $request->pagination())]);
        $items = array_map(fn ($m) => ['id' => $m->user_id, 'name' => $m->getRelation('user')->name], $data['page']->items());

        return new ApiResponse(PageResource::wrap($data['page'], $items));
    }
}
