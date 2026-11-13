<?php

namespace App\Services;

use App\Data\AttachmentUploadData;
use App\Models\Attachment;
use Closure;

/** Web與PAT共用保留→交易外IO→重新授權完成的上傳順序 */
class AttachmentTransferService
{
    /** 注入交易外物件IO服務
     * @param  AttachmentStorage  $storage  私有物件IO, 不取得HTTP或身分
     */
    public function __construct(private AttachmentStorage $storage) {}

    /** 保留與完成各自由呼叫端的可信交易邊界執行, IO不持有資料庫鎖
     * @template T
     *
     * @param  AttachmentUploadData  $file  已嗅探的本機暫存圖片
     * @param  Closure():Attachment  $reserve  鎖後重驗並保留配額
     * @param  Closure(Attachment):T  $complete  IO後鎖內重驗, 保存成功結果
     * @return T 最後完成結果或同請求原結果
     */
    public function upload(AttachmentUploadData $file, Closure $reserve, Closure $complete): mixed
    {
        $attachment = $reserve();
        if ($attachment->status === 'uploading') {
            $this->storage->put($attachment->storage_disk, $attachment->storage_key, $file->path);
        }

        return $complete($attachment);
    }
}
