<?php

namespace App\Services;

use App\Domain\DomainError;
use JsonException;

/**
 * 防重複提交原結果的版本化無損編碼。
 * 讀取永遠同時接受純JSON與envelope；寫入只有設定啟用、結果夠大且確實變小時才編碼。
 * 不認識的codec或內容損壞一律明確失敗，呼叫端不得當成找不到receipt而重新執行。
 */
class ReceiptCodec
{
    public const MARKER = '__receiptCodec';

    public const GZIP_V1 = 'gzip-v1';

    /**
     * 建立編碼設定；未指定時讀config。
     *
     * @param  string|null  $codec  寫入用codec（json＝不編碼）
     * @param  int|null  $minBytes  啟用編碼的最小JSON bytes
     */
    public function __construct(private ?string $codec = null, private ?int $minBytes = null)
    {
        $this->codec ??= (string) config('tasktracker.receipts.codec', 'json');
        $this->minBytes ??= (int) config('tasktracker.receipts.min_bytes', 4096);
    }

    /**
     * 依設定把原結果轉為可存入JSON欄位的值；小結果或壓縮收益不足時原樣保存。
     *
     * @param  array<mixed>  $result  原結果
     * @param  bool  $force  批次轉換時忽略codec設定，仍套用大小門檻
     * @return array<mixed> 原結果或envelope
     */
    public function encode(array $result, bool $force = false): array
    {
        if (! $force && $this->codec !== self::GZIP_V1) {
            return $result;
        }
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($json) < $this->minBytes) {
            return $result;
        }
        $envelope = [self::MARKER => self::GZIP_V1, 'sha256' => hash('sha256', $json), 'bytes' => strlen($json), 'data' => base64_encode(gzencode($json, 6))];
        // base64與欄位metadata也算進去，至少省兩成才值得改格式。
        if (strlen(json_encode($envelope, JSON_THROW_ON_ERROR)) > strlen($json) * 0.8) {
            return $result;
        }

        return $envelope;
    }

    /**
     * 還原保存值；純JSON原樣回傳，envelope驗證長度與checksum後回傳原結果。
     *
     * @param  mixed  $stored  欄位內容
     * @return array<mixed> 原結果
     *
     * @throws DomainError 不認識的codec或內容損壞
     */
    public function decode(mixed $stored): array
    {
        if (! is_array($stored) || ! array_key_exists(self::MARKER, $stored)) {
            return is_array($stored) ? $stored : [];
        }
        if ($stored[self::MARKER] !== self::GZIP_V1) {
            $this->unreadable();
        }
        $binary = is_string($stored['data'] ?? null) ? base64_decode($stored['data'], true) : false;
        $json = $binary === false ? false : @gzdecode($binary);
        if (! is_string($json) || strlen($json) !== ($stored['bytes'] ?? -1) || ! hash_equals((string) ($stored['sha256'] ?? ''), hash('sha256', $json))) {
            $this->unreadable();
        }
        try {
            $result = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->unreadable();
        }

        return is_array($result) ? $result : $this->unreadable();
    }

    /**
     * 判斷保存值是否為envelope。
     *
     * @param  mixed  $stored  欄位內容
     * @return bool 是否已編碼
     */
    public function isEncoded(mixed $stored): bool
    {
        return is_array($stored) && array_key_exists(self::MARKER, $stored);
    }

    /**
     * 保留key並明確失敗。
     *
     * @return never
     *
     * @throws DomainError 重播結果無法讀取
     */
    private function unreadable(): never
    {
        DomainError::fail('RECEIPT_UNREADABLE', '無法讀取此操作識別碼的原結果，未重新執行；請聯絡管理者');
    }
}
