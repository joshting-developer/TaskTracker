<?php

namespace App\Http\Controllers\Queries;

use App\Data\WorkspaceContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Queries\PageResource;
use App\Http\Resources\Queries\ProjectMemberResource;
use App\Http\WorkspaceTransaction;
use App\Services\ProjectAclReadService;
use Illuminate\Http\Request;

/** Project管理HTTP入口；metadata、成員、候選與歷史各自分責。 */
class ProjectMembersController extends Controller
{
    /**
     * 注入此Controller共用的交易邊界與應用用例，不在建構時取得租戶或查詢。
     *
     * @param  WorkspaceTransaction  $workspace  此Controller的穩定協作者
     * @param  ProjectAclReadService  $acl  此Controller的穩定協作者
     */
    public function __construct(private WorkspaceTransaction $workspace, private ProjectAclReadService $acl) {}

    /**
     * 讀取ACL版本及可授予角色，不附成員。
     *
     * @param  Request  $request  HTTP請求
     * @param  string  $project  專案公開ID
     * @return ApiResponse 管理metadata
     */
    public function metadata(Request $request, string $project): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->acl->metadata($context, $project)));
    }

    /**
     * 讀取當頁有效成員與操作能力。
     *
     * @param  PageQueryRequest  $request  有界分頁條件
     * @param  string  $project  專案公開ID
     * @return ApiResponse 成員摘要分頁
     */
    public function index(PageQueryRequest $request, string $project): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->acl->index($context, $project, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], ProjectMemberResource::collection($data['page']->items())->resolve()));
    }

    /**
     * 搜尋管理用途成員候選，不讀歷史。
     *
     * @param  PageQueryRequest  $request  有界搜尋條件
     * @param  string  $project  專案公開ID
     * @return ApiResponse 有效候選分頁
     */
    public function candidates(PageQueryRequest $request, string $project): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->acl->candidates($context, $project, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], array_map(fn ($m) => ['id' => $m->user_id, 'name' => $m->member_name, 'email' => $m->member_email], $data['page']->items())));
    }

    /**
     * 按需讀取授權歷史，不回傳成員全集。
     *
     * @param  PageQueryRequest  $request  有界歷史分頁
     * @param  string  $project  專案公開ID
     * @return ApiResponse 授權歷史分頁
     */
    public function history(PageQueryRequest $request, string $project): ApiResponse
    {
        $data = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->acl->history($context, $project, $request->pagination())]);

        return new ApiResponse(PageResource::wrap($data['page'], array_map(fn ($event) => [...$event->event, 'id' => $event->id, 'targetName' => $event->targetName], $data['page']->items())));
    }
}
