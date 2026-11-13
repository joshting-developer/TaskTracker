<?php

namespace App\Http\Resources\Attachments;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 安全metadata, 不輸出disk、key、checksum與內部外鍵 */
class AttachmentResource extends JsonResource
{
    /** 投影單筆, 不觸發lazy load
     * @param  Request  $request  HTTP請求
     * @return array 安全欄位與穩定reference
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'name' => $this->original_name, 'mime' => $this->mime_type, 'bytes' => $this->byte_size,
            'status' => $this->status, 'revision' => $this->revision, 'reference' => 'attachment:'.$this->public_id,
            'expiresAt' => $this->expires_at?->toISOString()];
    }
}
