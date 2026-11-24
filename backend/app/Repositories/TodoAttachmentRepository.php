<?php

namespace App\Repositories;

use App\Models\Todo;
use App\Models\TodoAttachment;

/** TT-294：紙條附件的SQL讀寫, 一律限定工作區 */
class TodoAttachmentRepository
{
    /** 同上傳者同requestId的既有紀錄, 供冪等重送
     * @param  int  $workspaceId  工作區內部ID
     * @param  string  $userId  上傳者
     * @param  string  $requestId  上傳識別碼
     * @return TodoAttachment|null 既有紀錄
     */
    public function uploadRequest(int $workspaceId, string $userId, string $requestId): ?TodoAttachment
    {
        return TodoAttachment::where('workspace_id', $workspaceId)->where('uploaded_by', $userId)->where('upload_request_id', $requestId)->first();
    }

    /** 工作區紙條附件總量與本人暫存數
     * @param  int  $workspaceId  工作區內部ID
     * @param  string  $userId  上傳者
     * @return array{bytes:int,pending:int} 配額使用量
     */
    public function quota(int $workspaceId, string $userId): array
    {
        $row = TodoAttachment::where('workspace_id', $workspaceId)->where('status', '!=', 'purged')->selectRaw('COALESCE(SUM(byte_size), 0) as bytes')
            ->selectRaw("SUM(CASE WHEN uploaded_by = ? AND status IN ('uploading', 'pending') THEN 1 ELSE 0 END) as pending", [$userId])->first();

        return ['bytes' => (int) $row->bytes, 'pending' => (int) $row->pending];
    }

    /** 建立紀錄
     * @param  array  $values  欄位
     * @return TodoAttachment 新紀錄
     */
    public function create(array $values): TodoAttachment
    {
        return TodoAttachment::create($values);
    }

    /** 更新紀錄
     * @param  TodoAttachment  $attachment  既有紀錄
     * @param  array  $values  欄位
     * @return TodoAttachment 更新後紀錄
     */
    public function save(TodoAttachment $attachment, array $values): TodoAttachment
    {
        $attachment->update($values);

        return $attachment;
    }

    /** 工作區內依公開ID讀取
     * @param  int  $workspaceId  工作區內部ID
     * @param  string  $id  附件公開ID
     * @return TodoAttachment|null 紀錄
     */
    public function find(int $workspaceId, string $id): ?TodoAttachment
    {
        return TodoAttachment::where('workspace_id', $workspaceId)->where('public_id', $id)->first();
    }

    /** 綁定前鎖定引用的附件
     * @param  int  $workspaceId  工作區內部ID
     * @param  array<int,string>  $ids  附件公開ID
     * @return array<string,TodoAttachment> 以公開ID索引
     */
    public function lockMany(int $workspaceId, array $ids): array
    {
        return TodoAttachment::where('workspace_id', $workspaceId)->whereIn('public_id', $ids)->lockForUpdate()->get()->keyBy('public_id')->all();
    }

    /** 紙條已綁定的圖片數
     * @param  Todo  $todo  紙條
     * @return int 數量
     */
    public function count(Todo $todo): int
    {
        return TodoAttachment::where('workspace_id', $todo->workspace_id)->where('todo_id', $todo->id)->where('status', 'active')->count();
    }

    /** 將自己的暫存圖片綁到紙條
     * @param  Todo  $todo  紙條
     * @param  array<int,int>  $ids  附件內部ID
     * @return void
     */
    public function bind(Todo $todo, array $ids): void
    {
        TodoAttachment::where('workspace_id', $todo->workspace_id)->whereIn('id', $ids)->update(['todo_id' => $todo->id, 'status' => 'active', 'expires_at' => null, 'updated_at' => now()]);
    }

    /** 已綁定附件所屬紙條的公開ID
     * @param  TodoAttachment  $attachment  已綁定附件
     * @return string|null 紙條公開ID
     */
    public function todoPublicId(TodoAttachment $attachment): ?string
    {
        return Todo::where('workspace_id', $attachment->workspace_id)->where('id', $attachment->todo_id)->value('public_id');
    }

    /** 依公開ID讀紙條
     * @param  int  $workspaceId  工作區內部ID
     * @param  string  $id  紙條公開ID
     * @return Todo|null 紙條
     */
    public function todo(int $workspaceId, string $id): ?Todo
    {
        return Todo::where('workspace_id', $workspaceId)->where('public_id', $id)->first();
    }

    /** 逾期暫存候選, 每批100筆
     * @param  int  $after  上一批最後內部ID
     * @return array<int,TodoAttachment> 候選
     */
    public function expired(int $after): array
    {
        return TodoAttachment::where('id', '>', $after)->whereIn('status', ['uploading', 'pending', 'purging'])->where('expires_at', '<=', now())->orderBy('id')->limit(100)->get(['id', 'workspace_id', 'public_id', 'storage_disk', 'storage_key'])->all();
    }

    /** 清理時重讀並鎖住, 防止與綁定競爭
     * @param  int  $id  內部ID
     * @return TodoAttachment|null 仍逾期的紀錄
     */
    public function expiredLocked(int $id): ?TodoAttachment
    {
        return TodoAttachment::where('id', $id)->whereIn('status', ['uploading', 'pending', 'purging'])->where('expires_at', '<=', now())->lockForUpdate()->first();
    }
}
