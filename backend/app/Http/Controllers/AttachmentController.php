<?php

namespace App\Http\Controllers;

use App\Data\WorkspaceContext;
use App\Http\Requests\Attachments\AttachmentChangeRequest;
use App\Http\Requests\Attachments\AttachmentUploadRequest;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Attachments\AttachmentResource;
use App\Http\Resources\Queries\PageResource;
use App\Http\Responses\AttachmentContentResponse;
use App\Http\WorkspaceTransaction;
use App\Services\AttachmentService;
use App\Services\AttachmentTransferService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** 附件HTTP協調, 物件IO只在資料庫交易外執行 */
class AttachmentController extends Controller
{
    /** 注入穩定協作者
     * @param  WorkspaceTransaction  $workspace  身分與交易邊界
     * @param  AttachmentService  $attachments  用例服務
     * @param  AttachmentTransferService  $transfers  共用上傳順序
     * @param  AttachmentContentResponse  $contentResponse  共用安全串流
     */
    public function __construct(private WorkspaceTransaction $workspace, private AttachmentService $attachments, private AttachmentTransferService $transfers, private AttachmentContentResponse $contentResponse) {}

    /** 只回傳已授權編輯器需要的限制
     * @param  Request  $request  Session請求
     * @param  string  $project  專案公開ID
     * @return ApiResponse 安全限制
     */
    public function limits(Request $request, string $project): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->attachments->limits($context, $project)));
    }

    /** 保留配額後上傳, 最後重驗資格
     * @param  AttachmentUploadRequest  $request  multipart圖片
     * @param  string  $project  Project公開ID
     * @return ApiResponse 安全metadata
     */
    public function upload(AttachmentUploadRequest $request, string $project): ApiResponse
    {
        $file = $request->toDto();
        $attachment = $this->transfers->upload($file,
            fn () => $this->workspace->write($request, fn (WorkspaceContext $context) => $this->attachments->reserve($context, $project, $file)),
            fn ($row) => $this->workspace->write($request, fn (WorkspaceContext $context) => $this->attachments->complete($context, $project, $row->public_id)));

        return new ApiResponse((new AttachmentResource($attachment))->resolve());
    }

    /** 單Issue有效附件分頁
     * @param  PageQueryRequest  $request  頁碼
     * @param  string  $project  Project公開ID
     * @param  string  $issue  Issue公開ID
     * @return ApiResponse 有界metadata
     */
    public function index(PageQueryRequest $request, string $project, string $issue): ApiResponse
    {
        $result = $this->workspace->read($request, fn (WorkspaceContext $context) => ['page' => $this->attachments->index($context, $project, $issue, $request->pagination()->page)]);
        $page = $result['page'];

        return new ApiResponse(PageResource::wrap($page, array_map(fn ($row) => (new AttachmentResource($row))->resolve(), $page->items())));
    }

    /** 只在當下授權後讀取私有物件, 不redirect外部URL
     * @param  Request  $request  Session請求
     * @param  string  $project  Project公開ID
     * @param  string  $attachment  附件公開ID
     * @return StreamedResponse 安全圖片串流
     */
    public function content(Request $request, string $project, string $attachment): StreamedResponse
    {
        $row = $this->workspace->read($request, fn (WorkspaceContext $context) => $this->attachments->content($context, $project, $attachment));

        return $this->contentResponse->make($row, $request->boolean('download'));
    }

    /** 綁定Issue
     * @param  AttachmentChangeRequest  $request  驗證條件
     * @param  string  $project  Project公開ID
     * @param  string  $attachment  附件公開ID
     * @return ApiResponse 有界結果
     */
    public function bind(AttachmentChangeRequest $request, string $project, string $attachment): ApiResponse
    {
        return new ApiResponse($this->workspace->write($request, fn (WorkspaceContext $context) => $this->attachments->change($context, $project, $attachment, $request->validated(), false)));
    }

    /** 標記刪除並保留30天後清理
     * @param  AttachmentChangeRequest  $request  驗證條件
     * @param  string  $project  Project公開ID
     * @param  string  $attachment  附件公開ID
     * @return ApiResponse 有界結果
     */
    public function destroy(AttachmentChangeRequest $request, string $project, string $attachment): ApiResponse
    {
        return new ApiResponse($this->workspace->write($request, fn (WorkspaceContext $context) => $this->attachments->change($context, $project, $attachment, $request->validated(), true)));
    }
}
