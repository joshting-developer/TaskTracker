<?php

namespace App\Services;

use App\Domain\DomainError;
use App\Factories\SmtpTransportFactory;

/**
 * 檢查SMTP設定與TLS認證，僅驗證連線而不寄信。
 */
class SmtpService
{
    /**
     * 注入SMTP連線的外部IO邊界。
     *
     * @param  SmtpTransportFactory  $transports  建立transport
     */
    public function __construct(private SmtpTransportFactory $transports) {}

    /**
     * 回傳SMTP是否已設定，不揭露認證秘密。
     *
     * @return array
     */
    public function status(): array
    {
        $configured = (bool) (config('mail.default') === 'smtp' && config('mail.mailers.smtp.host') && config('mail.from.address'));

        return [
            'configured' => $configured,
            'mode' => config('mail.default') === 'smtp' ? 'queue' : 'preview',
            'provider' => $configured ? 'SMTP' : '尚未設定',
            'canSend' => $configured && config('mail.default') === 'smtp',
        ];
    }

    /**
     * 驗證SMTP連線與認證，結束時清理連線。
     *
     * @return array
     */
    public function verify(): array
    {
        if (! $this->status()['configured']) {
            DomainError::fail('INVALID_INPUT', '請先在伺服器設定 MAIL_HOST 與 MAIL_FROM_ADDRESS');
        }
        $config = config('mail.mailers.smtp');
        $port = filter_var($config['port'], FILTER_VALIDATE_INT, ['options' => [
            'min_range' => 1,
            'max_range' => 65535,
        ]]);
        if (! $port) {
            DomainError::fail('INVALID_INPUT', 'SMTP_PORT 設定不正確');
        }
        $transport = $this->transports->make((string) $config['host'], $port, ($config['scheme'] ?? null) === 'smtps' ? true : null);
        $transport->setRequireTls(true);
        $transport->getStream()->setTimeout(10);
        if ($config['username']) {
            $transport->setUsername($config['username']);
            $transport->setPassword($config['password'] ?? '');
        }
        try {
            $transport->start();

            return [
                'verified' => true,
                'message' => 'SMTP 連線與認證通過, 沒有寄出信件；寄件者與收件者仍需實寄驗證',
            ];
        } catch (\Throwable) {
            DomainError::fail('SMTP_ERROR', 'SMTP 連線或認證失敗, 請檢查伺服器設定');
        } finally {
            try {
                $transport->stop();
            } catch (\Throwable) {
            }
        }
    }
}
