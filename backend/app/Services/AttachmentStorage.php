<?php

namespace App\Services;

use App\Domain\DomainError;
use Illuminate\Filesystem\FilesystemManager;
use Throwable;

/** 交易外私有物件IO, 固定使用metadata的disk與key */
class AttachmentStorage
{
    /** 注入既有Laravel Filesystem, 不自造供應商interface
     * @param  FilesystemManager  $files  Laravel disk管理
     */
    public function __construct(private FilesystemManager $files) {}

    /** 上傳經內容驗證的暫存檔
     * @param  string  $disk  私有disk
     * @param  string  $key  隨機伺服器key
     * @param  string  $path  已驗證暫存路徑
     * @return void
     */
    public function put(string $disk, string $key, string $path): void
    {
        $stream = false;
        try {
            $stream = @fopen($path, 'rb');
            if (! $stream || ! $this->files->disk($disk)->put($key, $stream, ['visibility' => 'private'])) {
                DomainError::fail('STORAGE_ERROR', '圖片暫時無法儲存, 請重試');
            }
        } catch (Throwable) {
            DomainError::fail('STORAGE_ERROR', '圖片暫時無法儲存, 請重試');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /** 授權後開啟私有內容串流
     * @param  string  $disk  實際disk
     * @param  string  $key  實際key
     * @return resource 已開啟串流
     */
    public function read(string $disk, string $key): mixed
    {
        try {
            $stream = $this->files->disk($disk)->readStream($key);
            if (is_resource($stream)) {
                return $stream;
            }
        } catch (Throwable) {
        }
        DomainError::fail('NOT_FOUND', '圖片不存在或已清除');
    }

    /** 清除可重試, 不存在亦視為完成
     * @param  string  $disk  實際disk
     * @param  string  $key  實際key
     * @return bool 是否成功
     */
    public function delete(string $disk, string $key): bool
    {
        try {
            return $this->files->disk($disk)->delete($key);
        } catch (Throwable) {
            return false;
        }
    }
}
