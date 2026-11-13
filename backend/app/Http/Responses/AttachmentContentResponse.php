<?php

namespace App\Http\Responses;

use App\Models\Attachment;
use App\Services\AttachmentStorage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** 已授權的私有圖片HTTP呈現, Web與PAT使用相同安全標頭 */
class AttachmentContentResponse
{
    /** 注入共用私有儲存服務
     * @param  AttachmentStorage  $storage  只使用Service開啟已授權物件
     */
    public function __construct(private AttachmentStorage $storage) {}

    /** 不redirect儲存URL, 以串流固定內容型別與安全下載檔名
     * @param  Attachment  $row  Service已授權的metadata, 不觸發查詢
     * @param  bool  $download  是否強制下載
     * @return StreamedResponse 私有no-store圖片串流
     */
    public function make(Attachment $row, bool $download): StreamedResponse
    {
        $stream = $this->storage->read($row->storage_disk, $row->storage_key);

        return response()->stream(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $row->mime_type, 'Content-Length' => (string) $row->byte_size,
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cross-Origin-Resource-Policy' => 'same-origin',
            'Content-Disposition' => HeaderUtils::makeDisposition($download ? 'attachment' : 'inline', $row->original_name, 'image'),
        ]);
    }
}
