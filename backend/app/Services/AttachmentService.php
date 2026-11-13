<?php

namespace App\Services;

use App\Data\AttachmentUploadData;
use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Factories\RecordStoreFactory;
use App\Models\Attachment;
use App\Models\Project;
use App\Repositories\AttachmentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/** 附件授權、配額及生命週期, 所有業務資料經Repository */
class AttachmentService
{
    /** 注入查詢、授權與receipt協作者
     * @param  AttachmentRepository  $attachments  metadata資料入口
     * @param  ProjectReadService  $projects  當次專案授權
     * @param  RecordStoreFactory  $stores  同租戶receipt入口
     * @param  RequestReplay  $replay  重播與簽章
     */
    public function __construct(private AttachmentRepository $attachments, private ProjectReadService $projects, private RecordStoreFactory $stores, private RequestReplay $replay) {}

    /** 編輯器取得與後端一致的限制, 不回傳私有儲存配置
     * @param  WorkspaceContext  $context  可信資格
     * @param  string  $projectId  專案公開ID
     * @return array 圖片限制
     */
    public function limits(WorkspaceContext $context, string $projectId): array
    {
        $this->projects->require($context, $projectId, 'issue.write');

        return ['maxBytes' => config('attachments.max_bytes'), 'maxPixels' => config('attachments.max_pixels'), 'perIssue' => config('attachments.per_issue'), 'pendingHours' => config('attachments.pending_hours'), 'mimeTypes' => ['image/png', 'image/jpeg', 'image/webp']];
    }

    /** 授權並保留上傳配額, IO由外層在交易外執行
     * @param  WorkspaceContext  $context  可信資格
     * @param  string  $projectId  專案公開ID
     * @param  AttachmentUploadData  $file  已嗅探圖片
     * @return Attachment uploading或已完成的同請求記錄
     */
    public function reserve(WorkspaceContext $context, string $projectId, AttachmentUploadData $file): Attachment
    {
        $project = $this->projects->require($context, $projectId, 'issue.write');
        $signature = hash('sha256', json_encode([$projectId, $file->name, $file->mime, $file->bytes, $file->checksum], JSON_THROW_ON_ERROR));
        $previous = $this->attachments->uploadRequest($project, $context->userId, $file->requestId);
        if ($previous) {
            if ($previous->upload_signature !== $signature) {
                DomainError::fail('REQUEST_CONFLICT', '上傳識別碼已用於其他內容');
            }
            $this->available($previous);

            return $previous;
        }
        $quota = $this->attachments->quota($project, $context->userId);
        if (config('attachments.workspace_bytes') < $quota['bytes'] + $file->bytes || $quota['pending'] >= config('attachments.pending_per_user')) {
            DomainError::fail('INVALID_INPUT', '附件配額已滿, 請清理暫存圖片');
        }
        $disk = config('attachments.disk');
        if (config('filesystems.disks.'.$disk.'.visibility') !== 'private' || config('filesystems.disks.'.$disk.'.serve', false)) {
            DomainError::fail('STORAGE_ERROR', '附件必須使用私有儲存配置');
        }
        $id = (string) Str::uuid();

        return $this->attachments->create(['public_id' => $id, 'workspace_id' => $project->workspace_id, 'project_id' => $project->id,
            'uploaded_by' => $context->userId, 'storage_disk' => config('attachments.disk'), 'storage_key' => $context->workspaceId.'/'.$projectId.'/'.$id,
            'mime_type' => $file->mime, 'byte_size' => $file->bytes, 'original_name' => $file->name, 'checksum' => $file->checksum,
            'status' => 'uploading', 'upload_request_id' => $file->requestId, 'upload_signature' => $signature, 'expires_at' => now()->addHours(config('attachments.pending_hours'))]);
    }

    /** IO完成後再驗資格, 不使過期或已綁定檔案倒退狀態
     * @param  WorkspaceContext  $context  重新驗證資格
     * @param  string  $projectId  專案公開ID
     * @param  string  $id  附件公開ID
     * @return Attachment 完成的metadata
     */
    public function complete(WorkspaceContext $context, string $projectId, string $id): Attachment
    {
        $project = $this->projects->require($context, $projectId, 'issue.write');
        $attachment = $this->find($project, $id);
        if ($attachment->uploaded_by !== $context->userId) {
            DomainError::fail('NOT_FOUND', '找不到附件');
        }
        $this->available($attachment);

        return $attachment->status === 'uploading' ? $this->attachments->save($attachment, ['status' => 'pending']) : $attachment;
    }

    /** 讀取需即時專案資格, pending僅上傳者可取
     * @param  WorkspaceContext  $context  可信資格
     * @param  string  $projectId  專案公開ID
     * @param  string  $id  附件公開ID
     * @return Attachment 已授權物件參照
     */
    public function content(WorkspaceContext $context, string $projectId, string $id): Attachment
    {
        $project = $this->projects->require($context, $projectId, 'issue.read');
        $attachment = $this->find($project, $id);
        $this->available($attachment);
        if ($attachment->status === 'uploading' || ($attachment->status === 'pending' && $attachment->uploaded_by !== $context->userId)) {
            DomainError::fail('NOT_FOUND', '找不到附件');
        }
        if ($attachment->status === 'active' && $this->issuePublicId($attachment, $project) === '') {
            DomainError::fail('NOT_FOUND', '找不到任務');
        }

        return $attachment;
    }

    /** 有界讀取Issue附件
     * @param  WorkspaceContext  $context  可信資格
     * @param  string  $projectId  專案公開ID
     * @param  string  $issueId  任務公開ID
     * @param  int  $page  頁碼
     * @return LengthAwarePaginator 有效附件
     */
    public function index(WorkspaceContext $context, string $projectId, string $issueId, int $page): LengthAwarePaginator
    {
        $project = $this->projects->require($context, $projectId, 'issue.read');
        $issue = $this->attachments->issue($project, $issueId) ?? DomainError::fail('NOT_FOUND', '找不到任務');

        return $this->attachments->page($project, $issue, $page);
    }

    /** 綁定與刪除都先驗當下資格再重播, 同交易更新Issue revision
     * @param  WorkspaceContext  $context  可信資格
     * @param  string  $projectId  專案公開ID
     * @param  string  $id  附件公開ID
     * @param  array  $input  requestId、expectedRevision、issueId與issueRevision
     * @param  bool  $delete  是否標記刪除
     * @return array 有界結果
     */
    public function change(WorkspaceContext $context, string $projectId, string $id, array $input, bool $delete): array
    {
        $project = $this->projects->require($context, $projectId, 'issue.write');
        $attachment = $this->find($project, $id);
        if ($attachment->issue_id === null && $attachment->uploaded_by !== $context->userId) {
            DomainError::fail('NOT_FOUND', '找不到附件');
        }

        return $this->replay->execute($this->stores->forContext($context), 'requests', $context->userId, $delete ? 'deleteAttachment' : 'bindAttachment', ['projectId' => $projectId, 'id' => $id, ...$input], function () use ($context, $project, $attachment, $input, $delete) {
            $this->available($attachment);
            if ($attachment->revision !== $input['expectedRevision']) {
                DomainError::fail('REVISION_CONFLICT', '附件已更新, 請重新載入');
            }
            $issue = null;
            if (! $delete || $attachment->status === 'active') {
                $issue = $this->attachments->issue($project, $input['issueId'] ?? '') ?? DomainError::fail('NOT_FOUND', '找不到任務');
                if ($attachment->status === 'active' && $attachment->issue_id !== $issue->id) {
                    DomainError::fail('INVALID_INPUT', '附件已綁定其他任務');
                }
                if ($issue->revision !== ($input['issueRevision'] ?? null)) {
                    DomainError::fail('REVISION_CONFLICT', '任務已更新, 請重新載入');
                }
            }
            if (! $delete && $attachment->status !== 'pending') {
                DomainError::fail('INVALID_INPUT', '附件尚未完成上傳或已綁定');
            }
            if (! $delete && $this->attachments->count($project, $issue) >= config('attachments.per_issue')) {
                DomainError::fail('INVALID_INPUT', '任務附件數量已達上限');
            }
            $this->attachments->save($attachment, ['status' => $delete ? 'deleted' : 'active', 'issue_id' => $issue?->id,
                'revision' => $attachment->revision + 1, 'deleted_at' => $delete ? now() : null,
                'expires_at' => $delete ? now()->addDays(config('attachments.retention_days')) : null]);
            if ($issue) {
                $this->attachments->touchIssue($issue, ['id' => (string) Str::uuid(), 'kind' => 'updated', 'actor' => $context->legacyIdentity()['user']['name'], 'at' => now()->utc()->toISOString(), 'changes' => [['field' => 'attachments', 'before' => '', 'after' => $attachment->public_id, 'action' => $delete ? 'deleted' : 'bound']]]);
            }

            return ['id' => $attachment->public_id, 'revision' => $attachment->revision, 'status' => $attachment->status, 'issueId' => $issue?->public_id ?? '', 'issueRevision' => $issue ? $issue->revision + 1 : null];
        });
    }

    /** 同scope找附件
     * @param  Project  $project  授權Project
     * @param  string  $id  公開ID
     * @return Attachment 單筆或404
     */
    private function find(Project $project, string $id): Attachment
    {
        return $this->attachments->find($project, $id) ?? DomainError::fail('NOT_FOUND', '找不到附件');
    }

    /** 不提供已刪、清除或過期附件
     * @param  Attachment  $attachment  單筆metadata
     * @return void
     */
    private function available(Attachment $attachment): void
    {
        if (in_array($attachment->status, ['deleted', 'purging', 'purged'], true) || ($attachment->expires_at && $attachment->expires_at->isPast())) {
            DomainError::fail('NOT_FOUND', '附件已失效');
        }
    }

    /** 以Repository解析附件的同scope Issue公開ID
     * @param  Attachment  $attachment  有效附件
     * @param  Project  $project  已授權專案
     * @return string 任務公開ID或空字串
     */
    private function issuePublicId(Attachment $attachment, Project $project): string
    {
        return $this->attachments->issuePublicId($project, $attachment->issue_id) ?? '';
    }
}
