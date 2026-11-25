<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PersonalDashboardRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\PersonalDashboardResource;
use App\Http\WorkspaceTransaction;
use App\Services\PersonalDashboardService;

/** Session首頁摘要入口, 與公開PAT介面分開。 */
class PersonalDashboardController extends Controller
{
    /**
     * 注入可信工作區邊界與摘要用例, 建構時不查資料。
     *
     * @param  WorkspaceTransaction  $workspace  工作區交易邊界
     * @param  PersonalDashboardService  $dashboard  首頁摘要用例
     */
    public function __construct(private WorkspaceTransaction $workspace, private PersonalDashboardService $dashboard) {}

    /**
     * 每次讀取先重驗目前工作區資格, 只回傳指定摘要分頁。
     *
     * @param  PersonalDashboardRequest  $request  已驗證摘要種類與分頁
     * @return ApiResponse 有界摘要結果
     */
    public function index(PersonalDashboardRequest $request): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->dashboard->index($context, $request->validated('kind'), $request->pagination(), $request->safe()->except(['kind', 'page', 'perPage']))]);

        return new ApiResponse(PageResource::wrap($data['page'], PersonalDashboardResource::collection($data['page']->items())->resolve()));
    }
}
