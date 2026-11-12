<?php

namespace App\Http\Controllers\Queries;

use App\Http\ApiExecution;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\ProjectMemberResource;
use App\Services\ProjectAclReadService;
use Illuminate\Http\Request;

/** Project管理HTTP入口；metadata、成員、候選與歷史各自分責。 */
class ProjectMembersController extends Controller
{
    /**
     * 讀取ACL版本及可授予角色，不附成員。
     *
     * @param  Request  $request  HTTP請求
     * @param  string  $project  專案公開ID
     * @param  ApiExecution  $execution  可信交易邊界
     * @param  ProjectAclReadService  $acl  ACL查詢用例
     * @return ApiResponse 管理metadata
     */
    public function metadata(Request $request, string $project, ApiExecution $execution, ProjectAclReadService $acl): ApiResponse
    {
        return new ApiResponse($execution->query($request, fn ($actor) => $acl->metadata($actor, $project)));
    }

    /**
     * 讀取當頁有效成員與操作能力。
     *
     * @param  PageQueryRequest  $request  有界分頁條件
     * @param  string  $project  專案公開ID
     * @param  ApiExecution  $execution  可信交易邊界
     * @param  ProjectAclReadService  $acl  ACL查詢用例
     * @return ApiResponse 成員摘要分頁
     */
    public function index(PageQueryRequest $request, string $project, ApiExecution $execution, ProjectAclReadService $acl): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['page' => $acl->index($actor, $project, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], ProjectMemberResource::collection($data['page']->items())->resolve()));
    }

    /**
     * 搜尋管理用途成員候選，不讀歷史。
     *
     * @param  PageQueryRequest  $request  有界搜尋條件
     * @param  string  $project  專案公開ID
     * @param  ApiExecution  $execution  可信交易邊界
     * @param  ProjectAclReadService  $acl  ACL查詢用例
     * @return ApiResponse 有效候選分頁
     */
    public function candidates(PageQueryRequest $request, string $project, ApiExecution $execution, ProjectAclReadService $acl): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['page' => $acl->candidates($actor, $project, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], array_map(fn ($m) => ['id' => $m->user_id, 'name' => $m->member_name, 'email' => $m->member_email], $data['page']->items())));
    }

    /**
     * 按需讀取授權歷史，不回傳成員全集。
     *
     * @param  PageQueryRequest  $request  有界歷史分頁
     * @param  string  $project  專案公開ID
     * @param  ApiExecution  $execution  可信交易邊界
     * @param  ProjectAclReadService  $acl  ACL查詢用例
     * @return ApiResponse 授權歷史分頁
     */
    public function history(PageQueryRequest $request, string $project, ApiExecution $execution, ProjectAclReadService $acl): ApiResponse
    {
        $data = $execution->query($request, fn ($actor) => ['page' => $acl->history($actor, $project, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], array_map(fn ($event) => [...$event->event, 'id' => $event->id, 'targetName' => $event->targetName], $data['page']->items())));
    }
}
