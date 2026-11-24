<?php

namespace App\Http\Controllers;

use App\Data\WorkspaceContext;
use App\Http\Requests\Attachments\AttachmentUploadRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Attachments\AttachmentResource;
use App\Http\Responses\AttachmentContentResponse;
use App\Http\WorkspaceTransaction;
use App\Services\AttachmentTransferService;
use App\Services\TodoAttachmentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** TT-294：紙條私有圖片的Session端點, 不提供PAT */
class TodoAttachmentController extends Controller
{
    /** 注入交易邊界、規則與串流
     * @param  WorkspaceTransaction  $workspace  工作區交易邊界
     * @param  TodoAttachmentService  $attachments  紙條附件規則
     * @param  AttachmentTransferService  $transfers  交易外上傳IO
     * @param  AttachmentContentResponse  $contentResponse  私有串流
     */
    public function __construct(private WorkspaceTransaction $workspace, private TodoAttachmentService $attachments, private AttachmentTransferService $transfers, private AttachmentContentResponse $contentResponse) {}

    /** 編輯器限制
     * @param  Request  $request  HTTP請求
     * @return ApiResponse 圖片限制
     */
    public function limits(Request $request): ApiResponse
    {
        return new ApiResponse($this->workspace->read($request, fn (WorkspaceContext $context) => $this->attachments->limits($context)));
    }

    /** 上傳暫存圖片, 綁定由建立／修改紙條與附言負責
     * @param  AttachmentUploadRequest  $request  已驗證檔案與requestId
     * @return ApiResponse 附件metadata
     */
    public function upload(AttachmentUploadRequest $request): ApiResponse
    {
        $file = $request->toDto();
        $attachment = $this->transfers->upload($file,
            fn () => $this->workspace->write($request, fn (WorkspaceContext $context) => $this->attachments->reserve($context, $file)),
            fn ($row) => $this->workspace->write($request, fn (WorkspaceContext $context) => $this->attachments->complete($context, $row->public_id)));

        return new ApiResponse((new AttachmentResource($attachment))->resolve());
    }

    /** 授權後串流圖片
     * @param  Request  $request  HTTP請求
     * @param  string  $attachment  附件公開ID
     * @return StreamedResponse 私有no-store圖片
     */
    public function content(Request $request, string $attachment): StreamedResponse
    {
        $row = $this->workspace->read($request, fn (WorkspaceContext $context) => $this->attachments->content($context, $attachment));

        return $this->contentResponse->make($row, false);
    }
}
