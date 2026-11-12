<?php

namespace App\Factories;

use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * 建立SMTP連線的外部IO邊界；測試可替換成不連線的實作，正式流程只用來verify，不寄信。
 */
class SmtpTransportFactory
{
    /**
     * 建立指定主機的ESMTP transport。
     *
     * @param  string  $host  SMTP主機
     * @param  int  $port  埠號
     * @param  bool|null  $secure  是否直接以TLS連線；null交給Symfony依埠號判斷（465自動TLS）
     * @return EsmtpTransport 尚未start的transport
     */
    public function make(string $host, int $port, ?bool $secure): EsmtpTransport
    {
        return new EsmtpTransport($host, $port, $secure);
    }
}
