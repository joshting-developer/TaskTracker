<?php

namespace App\Data;

use App\Domain\DomainError;
use Illuminate\Http\UploadedFile;

/** 已嗅探的附件metadata與暫存路徑, 不接受外部URL或使用者storage key */
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

    /** 驗實際附件內容與各格式獨立上限, 不信任前端MIME
     * @param  UploadedFile  $file  PHP上傳檔
     * @param  string  $requestId  已驗UUID
     * @return self 已驗證附件
     */
    public static function from(UploadedFile $file, string $requestId): self
    {
        $path = $file->getRealPath();
        $bytes = $file->getSize();
        $mime = $path ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        $extensions = ['image/png' => ['png'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/webp' => ['webp'], 'application/pdf' => ['pdf'], 'application/zip' => ['zip']];
        $isImage = is_string($mime) && str_starts_with($mime, 'image/');
        $limit = config($isImage ? 'attachments.max_bytes' : 'attachments.general_max_bytes');
        if (! $file->isValid() || ! $path || ! isset($extensions[$mime]) || $bytes < 1 || $bytes > $limit
            || ! in_array(strtolower($file->getClientOriginalExtension()), $extensions[$mime], true)) {
            DomainError::fail('INVALID_INPUT', '附件格式或大小不符合限制');
        }
        if ($isImage) {
            $image = @getimagesize($path);
            if (! $image || ($image['mime'] ?? '') !== $mime || $image[0] < 1 || $image[1] < 1 || config('attachments.max_pixels') < $image[0] * $image[1]) {
                DomainError::fail('INVALID_INPUT', '圖片格式、大小或尺寸不符合限制');
            }
        } elseif ($mime === 'application/zip') {
            self::checkZip($path);
        } else {
            $stream = fopen($path, 'rb');
            try {
                $header = fread($stream, 8);
                fseek($stream, max(0, $bytes - 1024));
                $tail = stream_get_contents($stream);
                if (! preg_match('/^%PDF-[12]\.[0-9]/', $header) || ! preg_match('/%%EOF\s*$/', $tail)) {
                    DomainError::fail('INVALID_INPUT', 'PDF結構不符合限制');
                }
            } finally {
                fclose($stream);
            }
        }
        $name = mb_substr(preg_replace('/[\x00-\x1f\x7f\\\\\/]/u', '_', $file->getClientOriginalName()) ?? 'image.'.$extensions[$mime][0], 0, 200);

        return new self($requestId, $path, $name, $mime, $bytes, hash_file('sha256', $path));
    }

    /** 只讀ZIP目錄檢查資源上限與路徑, 不解壓或執行任何entry
     *
     * @param  string  $path  PHP暫存檔
     * @return void
     */
    private static function checkZip(string $path): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::RDONLY | \ZipArchive::CHECKCONS) !== true) {
            DomainError::fail('INVALID_INPUT', 'ZIP結構不符合限制');
        }
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > config('attachments.zip_max_entries')) {
                DomainError::fail('INVALID_INPUT', 'ZIP項目數超過限制');
            }
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = $stat['name'] ?? '';
                $os = 0;
                $attributes = 0;
                $zip->getExternalAttributesIndex($index, $os, $attributes);
                $symlink = $os === \ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000;
                if ($symlink || ! $stat || $name === '' || str_contains($name, "\0") || preg_match('~(^/|^[a-z]:|\\\\|(^|/)\.\.(/|$))~i', $name)
                    || ($stat['encryption_method'] ?? 0) !== 0 || ($stat['size'] ?? -1) < 0 || ($stat['comp_size'] ?? -1) < 0
                    || $stat['size'] > config('attachments.zip_max_expanded_bytes')
                    || $stat['size'] > max(1, $stat['comp_size']) * config('attachments.zip_max_ratio')) {
                    DomainError::fail('INVALID_INPUT', 'ZIP內容、路徑或壓縮比不符合限制');
                }
                $expanded += $stat['size'];
                if ($expanded > config('attachments.zip_max_expanded_bytes')) {
                    DomainError::fail('INVALID_INPUT', 'ZIP解壓大小超過限制');
                }
            }
        } finally {
            $zip->close();
        }
    }
}
