<?php

namespace App\Http\Controllers\Integrations;

use App\Data\TokenContext;
use App\Http\Controllers\Controller;
use App\Http\Integrations\BearerTokenSession;
use App\Http\Requests\Attachments\AttachmentChangeRequest;
use App\Http\Requests\Attachments\AttachmentUploadRequest;
use App\Http\Requests\PageQueryRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\Attachments\AttachmentResource;
use App\Http\Resources\Queries\PageResource;
use App\Http\Responses\AttachmentContentResponse;
use App\Services\AttachmentService;
use App\Services\AttachmentTransferService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** 純PAT附件適配器, 業務規則與私有儲存沿用Web服務 */
class PublicAttachmentsController extends Controller
{
    /** 注入Bearer與共用附件服務
     * @param  BearerTokenSession  $tokens  Scope、資格及交易邊界
     * @param  AttachmentService  $attachments  共用附件用例
     * @param  AttachmentTransferService  $transfers  交易外IO順序
     * @param  AttachmentContentResponse  $contentResponse  共用HTTP串流
     */
    public function __construct(private BearerTokenSession $tokens, private AttachmentService $attachments, private AttachmentTransferService $transfers, private AttachmentContentResponse $contentResponse) {}

    /** 取得目前專案的上傳限制
     * @param  Request  $request  純Bearer請求
     * @param  string  $project  專案公開ID
     * @return ApiResponse 編輯者可取得的上傳限制
     */
    public function limits(Request $request, string $project): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, fn (TokenContext $token) => $this->attachments->limits($token->workspace, $project), $project, 'issue.write'));
    }

    /** 保留與完成各自鎖後重驗, 失敗保留同requestId可補送的uploading記錄
     * @param  AttachmentUploadRequest  $request  multipart圖片, 不依賴Session
     * @param  string  $project  專案公開ID
     * @return ApiResponse 成功原結果metadata, 不附storage key
     */
    public function upload(AttachmentUploadRequest $request, string $project): ApiResponse
    {
        $file = $request->toDto();
        $input = ['projectId' => $project, 'requestId' => $file->requestId, 'name' => $file->name, 'mime' => $file->mime, 'bytes' => $file->bytes, 'checksum' => $file->checksum];
        $result = $this->transfers->upload($file,
            fn () => $this->tokens->stageUpload($request, fn (TokenContext $token) => $this->attachments->reserve($token->workspace, $project, $file), $project),
            fn ($row) => $this->tokens->writeOnce($request, 'public.v1.uploadAttachment', $input,
                fn (TokenContext $token) => $this->attachments->reserve($token->workspace, $project, $file),
                fn (TokenContext $token) => (new AttachmentResource($this->attachments->complete($token->workspace, $project, $row->public_id)))->resolve(), $project));

        return new ApiResponse($result);
    }

    /** 取得單筆可讀metadata
     * @param  Request  $request  純Bearer請求
     * @param  string  $project  專案公開ID
     * @param  string  $attachment  附件公開ID
     * @return ApiResponse 當下可讀的metadata, pending僅上傳者
     */
    public function show(Request $request, string $project, string $attachment): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, fn (TokenContext $token) => (new AttachmentResource($this->attachments->content($token->workspace, $project, $attachment)))->resolve(), $project));
    }

    /** 分頁取得同卡有效附件
     * @param  PageQueryRequest  $request  分頁輸入
     * @param  string  $project  專案公開ID
     * @param  string  $issue  任務公開ID
     * @return ApiResponse 單卡每頁20筆metadata
     */
    public function index(PageQueryRequest $request, string $project, string $issue): ApiResponse
    {
        return new ApiResponse($this->tokens->read($request, function (TokenContext $token) use ($request, $project, $issue) {
            $page = $this->attachments->index($token->workspace, $project, $issue, $request->pagination()->page);

            return PageResource::wrap($page, array_map(fn ($row) => (new AttachmentResource($row))->resolve(), $page->items()));
        }, $project));
    }

    /** 重新授權後串流私有內容
     * @param  Request  $request  純Bearer請求
     * @param  string  $project  專案公開ID
     * @param  string  $attachment  附件公開ID
     * @return StreamedResponse 授權後串流, 不redirect外部物件URL
     */
    public function content(Request $request, string $project, string $attachment): StreamedResponse
    {
        $row = $this->tokens->read($request, fn (TokenContext $token) => $this->attachments->content($token->workspace, $project, $attachment), $project);

        return $this->contentResponse->make($row, $request->boolean('download'));
    }

    /** 原子綁定附件與卡片
     * @param  AttachmentChangeRequest  $request  雙revision與requestId
     * @param  string  $project  專案公開ID
     * @param  string  $attachment  附件公開ID
     * @return ApiResponse 原子綁定結果, 重播前重新驗Scope與資源資格
     */
    public function bind(AttachmentChangeRequest $request, string $project, string $attachment): ApiResponse
    {
        $input = ['projectId' => $project, 'id' => $attachment, ...$request->validated()];

        return new ApiResponse($this->tokens->writeOnce($request, 'public.v1.bindAttachment', $input,
            fn (TokenContext $token) => $this->attachments->content($token->workspace, $project, $attachment),
            fn (TokenContext $token) => $this->attachments->change($token->workspace, $project, $attachment, $request->validated(), false), $project));
    }
}
