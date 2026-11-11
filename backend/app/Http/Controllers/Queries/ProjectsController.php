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
}
