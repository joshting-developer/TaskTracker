<?php

namespace App\Data;

use App\Domain\DomainError;
use Illuminate\Http\UploadedFile;

/** 已嗅探的圖片metadata與暫存路徑, 不接受外部URL或使用者storage key */
final readonly class AttachmentUploadData
{
    /** 保存伺服器檢查結果
     * @param  string  $requestId  冪等請求ID
     * @param  string  $path  PHP暫存檔
     * @param  string  $name  安全檔名
     * @param  string  $mime  嗅探MIME
     * @param  int  $bytes  實際bytes
     * @param  string  $checksum  SHA256
     */
    private function __construct(public string $requestId, public string $path, public string $name, public string $mime, public int $bytes, public string $checksum) {}

    /** 驗實際圖片內容與像素上限, 不信任前端MIME
     * @param  UploadedFile  $file  PHP上傳檔
     * @param  string  $requestId  已驗UUID
     * @return self 已驗證圖片
     */
    public static function from(UploadedFile $file, string $requestId): self
    {
        $path = $file->getRealPath();
        $bytes = $file->getSize();
        $image = $path ? @getimagesize($path) : false;
        $mime = $path ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        $extensions = ['image/png' => ['png'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/webp' => ['webp']];
        if (! $file->isValid() || ! $image || ! isset($extensions[$mime]) || ($image['mime'] ?? '') !== $mime
            || ! in_array(strtolower($file->getClientOriginalExtension()), $extensions[$mime], true)
            || $bytes < 1 || $bytes > config('attachments.max_bytes') || $image[0] < 1 || $image[1] < 1
            || config('attachments.max_pixels') < $image[0] * $image[1]) {
            DomainError::fail('INVALID_INPUT', '圖片格式、大小或尺寸不符合限制');
        }
        $name = mb_substr(preg_replace('/[\x00-\x1f\x7f\\\\\/]/u', '_', $file->getClientOriginalName()) ?? 'image.'.$extensions[$mime][0], 0, 200);

        return new self($requestId, $path, $name, $mime, $bytes, hash_file('sha256', $path));
    }
}
