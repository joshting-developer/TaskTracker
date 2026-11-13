<?php

namespace App\Services;

use Illuminate\Contracts\Mail\Factory;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/** SMTP預先連線邊界, 確定尚未交付信件時的失敗才允許自動重試 */
class MailConnectionService
{
    /** 注入Laravel Mail唯一設定入口
     * @param  Factory  $mail  Laravel Mail manager
     */
    public function __construct(private Factory $mail) {}

    /** 預先完成TLS／AUTH, 不交付任何信件
     * @return void
     */
    public function connect(): void
    {
        if (config('mail.default') !== 'smtp') {
            throw new RuntimeException('MAIL_CONFIGURATION_INVALID');
        }
        $transport = $this->mail->mailer('smtp')->getSymfonyTransport();
        if (! $transport instanceof EsmtpTransport) {
            throw new RuntimeException('MAIL_CONFIGURATION_INVALID');
        }
        $transport->setRequireTls(true);
        $transport->getStream()->setTimeout(10);
        try {
            $transport->start();
        } catch (\Throwable) {
            // 不保留原SMTP例外鏈, 避免failed_jobs或log輸出認證資訊。
            throw new RuntimeException('SMTP_CONNECTION_FAILED');
        }
    }
}
