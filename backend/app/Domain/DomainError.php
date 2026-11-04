<?php

namespace App\Domain;

use RuntimeException;

/**
 * 承載可對外呈現的穩定業務錯誤碼與訊息。
 */
class DomainError extends RuntimeException
{
    /**
     * 設定穩定的業務錯誤碼與對外訊息。
     *
     * @param  string  $errorCode  可對外呈現的穩定錯誤碼
     * @param  string  $message  可對外呈現的錯誤說明
     * @return void
     */
    public function __construct(public string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    /**
     * 拋出穩定的業務錯誤並停止目前操作。
     *
     * @param  string  $code  可對外呈現的穩定錯誤碼
     * @param  string  $message  可對外呈現的錯誤說明
     * @return never
     */
    public static function fail(string $code, string $message): never
    {
        throw new self($code, $message);
    }
}
