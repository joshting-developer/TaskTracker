<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions;
use App\Data\AttachmentUploadData;
use App\Data\PersonalReadContext;
use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Models\TodoAttachment;
use App\Models\WorkspaceMember;
use App\Repositories\TodoAttachmentRepository;
use App\Repositories\TodoCollaborationRepository;
use Illuminate\Support\Str;

/**
 * TT-294：紙條私有圖片。讀取資格＝能讀該紙條的經手者, 不以Project讀權或工作區Owner放行；
 * 暫存只屬上傳者, 由建立／修改紙條與附言在同交易依Markdown引用綁定。
 */
class TodoAttachmentService
{
    /** 引用格式與Issue附件相同 */
    private const REFERENCE = '/!\[[^\]\r\n]*\]\(attachment:([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})\)/i';

    /** 注入資料、資格與儲存
     * @param  TodoAttachmentRepository  $attachments  紙條附件SQL
     * @param  TodoCollaborationRepository  $todos  成員與經手資格
     * @param  AttachmentStorage  $storage  私有儲存
     */
    public function __construct(private TodoAttachmentRepository $attachments, private TodoCollaborationRepository $todos, private AttachmentStorage $storage) {}

    /** 編輯器取得與後端一致的限制
     * @param  WorkspaceContext  $context  可信資格
     * @return array 圖片限制
     */
    public function limits(WorkspaceContext $context): array
    {
        $this->member($context, 'todo.read');

        // 與Issue附件同形狀, 但不提供一般附件（PDF／ZIP）。
        return ['maxBytes' => config('attachments.max_bytes'), 'maxPixels' => config('attachments.max_pixels'), 'perIssue' => config('attachments.per_issue'), 'pendingHours' => config('attachments.pending_hours'), 'mimeTypes' => ['image/png', 'image/jpeg', 'image/webp'], 'general' => null];
    }

    /** 授權並保留上傳配額, IO由外層在交易外執行；只接受圖片
     * @param  WorkspaceContext  $context  可信資格
     * @param  AttachmentUploadData  $file  已嗅探檔案
     * @return TodoAttachment uploading或同請求既有紀錄
     */
    public function reserve(WorkspaceContext $context, AttachmentUploadData $file): TodoAttachment
    {
        $member = $this->member($context, 'todo.writeOwn');
        if (! str_starts_with($file->mime, 'image/') || $file->bytes > config('attachments.max_bytes')) {
            DomainError::fail('INVALID_INPUT', '紙條目前只接受限制大小內的圖片');
        }
        $signature = hash('sha256', json_encode(['todo', $file->name, $file->mime, $file->bytes, $file->checksum], JSON_THROW_ON_ERROR));
        $previous = $this->attachments->uploadRequest($member->workspace_id, $context->userId, $file->requestId);
        if ($previous) {
            if ($previous->upload_signature !== $signature) {
                DomainError::fail('REQUEST_CONFLICT', '上傳識別碼已用於其他內容');
            }
            $this->available($previous);

            return $previous;
        }
        $quota = $this->attachments->quota($member->workspace_id, $context->userId);
        if (config('attachments.workspace_bytes') < $quota['bytes'] + $file->bytes || $quota['pending'] >= config('attachments.pending_per_user')) {
            DomainError::fail('INVALID_INPUT', '附件配額已滿, 請清理暫存圖片');
        }
        $disk = config('attachments.disk');
        $this->storage->assertConfigured($disk);
        $id = (string) Str::uuid();

        return $this->attachments->create(['public_id' => $id, 'workspace_id' => $member->workspace_id, 'uploaded_by' => $context->userId,
            'storage_disk' => $disk, 'storage_key' => $context->workspaceId.'/todos/'.$id, 'mime_type' => $file->mime, 'byte_size' => $file->bytes,
            'original_name' => $file->name, 'checksum' => $file->checksum, 'status' => 'uploading', 'upload_request_id' => $file->requestId,
            'upload_signature' => $signature, 'expires_at' => now()->addHours(config('attachments.pending_hours'))]);
    }

    /** IO完成後再驗資格, 不使過期或已綁定檔案倒退狀態
     * @param  WorkspaceContext  $context  重新驗證資格
     * @param  string  $id  附件公開ID
     * @return TodoAttachment 完成的metadata
     */
    public function complete(WorkspaceContext $context, string $id): TodoAttachment
    {
        $member = $this->member($context, 'todo.writeOwn');
        $attachment = $this->attachments->find($member->workspace_id, $id);
        if (! $attachment || $attachment->uploaded_by !== $context->userId || ! in_array($attachment->status, ['uploading', 'pending', 'active'], true)) {
            DomainError::fail('NOT_FOUND', '找不到附件');
        }
        $this->available($attachment);

        return $attachment->status === 'uploading' ? $this->attachments->save($attachment, ['status' => 'pending']) : $attachment;
    }

    /** 暫存只給上傳者；已綁定需即時能讀所屬紙條
     * @param  WorkspaceContext  $context  可信資格
     * @param  string  $id  附件公開ID
     * @return TodoAttachment 已授權物件參照
     */
    public function content(WorkspaceContext $context, string $id): TodoAttachment
    {
        $member = $this->member($context, 'todo.read');
        $attachment = $this->attachments->find($member->workspace_id, $id) ?? DomainError::fail('NOT_FOUND', '找不到附件');
        $this->available($attachment);
        // 只放行暫存（限上傳者）與已綁定；uploading、purging、purged 一律視同不存在。
        if (! in_array($attachment->status, ['pending', 'active'], true) || ($attachment->status === 'pending' && $attachment->uploaded_by !== $context->userId)) {
            DomainError::fail('NOT_FOUND', '找不到附件');
        }
        if ($attachment->status === 'active') {
            // 與紙條讀取相同：建立人、原承辦、目前承辦或曾經手, 不以Owner身分放行。
            $personal = new PersonalReadContext($member->workspace_id, $member->id, $context->userId);
            $todo = $this->attachments->todoPublicId($attachment);
            $row = $todo ? $this->todos->read($personal, $todo) : null;
            if (! $row || ! $this->todos->participates($personal, $row)) {
                DomainError::fail('NOT_FOUND', '找不到附件');
            }
        }

        return $attachment;
    }

    /** 同建立／修改紙條或附言交易, 綁定Markdown引用的本人暫存圖片；已綁在同紙條者略過
     * @param  array  $identity  交易內可信身分
     * @param  string  $todoId  已寫入的紙條公開ID
     * @param  string  $markdown  經長度驗證的Markdown
     * @return void
     */
    public function bindMarkdownReferences(array $identity, string $todoId, string $markdown): void
    {
        preg_match_all(self::REFERENCE, $markdown, $matches);
        $ids = array_values(array_unique(array_map('strtolower', $matches[1])));
        if (! $ids) {
            return;
        }
        if (count($ids) > config('attachments.per_issue')) {
            DomainError::fail('INVALID_INPUT', '附件數量已達上限');
        }
        $member = $this->todos->member($identity['workspace']['id'], $identity['user']['id']) ?? DomainError::fail('UNAUTHENTICATED', '工作空間資格已失效');
        $todo = $this->attachments->todo($member->workspace_id, $todoId) ?? DomainError::fail('NOT_FOUND', '找不到待辦');
        $rows = $this->attachments->lockMany($member->workspace_id, $ids);
        $pending = [];
        foreach ($ids as $id) {
            $row = $rows[$id] ?? DomainError::fail('NOT_FOUND', '找不到可用附件');
            if ($row->status === 'active' && $row->todo_id === $todo->id) {
                continue;
            }
            if ($row->status !== 'pending' || $row->uploaded_by !== $identity['user']['id'] || $row->todo_id !== null || ! $row->expires_at || $row->expires_at->isPast()) {
                DomainError::fail('NOT_FOUND', '找不到可用附件');
            }
            $pending[] = $row->id;
        }
        if ($pending) {
            if ($this->attachments->count($todo) + count($pending) > config('attachments.per_issue')) {
                DomainError::fail('INVALID_INPUT', '附件數量已達上限');
            }
            $this->attachments->bind($todo, $pending);
        }
    }

    /** 重驗有效成員與權限
     * @param  WorkspaceContext  $context  可信資格
     * @param  string  $permission  必要權限
     * @return WorkspaceMember 有效成員
     */
    private function member(WorkspaceContext $context, string $permission): WorkspaceMember
    {
        WorkspacePermissions::authorize($context->legacyIdentity(), 'todo.read');
        WorkspacePermissions::authorize($context->legacyIdentity(), $permission);

        return $this->todos->member($context->workspaceId, $context->userId) ?? DomainError::fail('UNAUTHENTICATED', '工作空間資格已失效');
    }

    /** 過期暫存視同不存在
     * @param  TodoAttachment  $attachment  紀錄
     * @return void
     */
    private function available(TodoAttachment $attachment): void
    {
        if (in_array($attachment->status, ['uploading', 'pending'], true) && (! $attachment->expires_at || $attachment->expires_at->isPast())) {
            DomainError::fail('NOT_FOUND', '找不到附件');
        }
    }
}
