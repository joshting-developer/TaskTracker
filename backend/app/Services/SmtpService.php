<?php

namespace App\Services;

use App\Domain\DomainError;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SmtpService
{
    public function status(): array
    {
        $configured = (bool) (config('tasktracker.smtp.host') && config('tasktracker.smtp.from'));
        return ['configured' => $configured, 'mode' => 'preview', 'provider' => $configured ? 'SMTP' : '尚未設定', 'canSend' => false];
    }

    public function verify(): array
    {
        if (!$this->status()['configured']) DomainError::fail('INVALID_INPUT', '請先在伺服器設定 SMTP_HOST 與 SMTP_FROM');
        $config = config('tasktracker.smtp');
        $port = filter_var($config['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if (!$port) DomainError::fail('INVALID_INPUT', 'SMTP_PORT 設定不正確');
        $transport = new EsmtpTransport($config['host'], $port, $config['secure']);
        $transport->setRequireTls(true);
        $transport->getStream()->setTimeout(10);
        if ($config['user']) { $transport->setUsername($config['user']); $transport->setPassword($config['password'] ?? ''); }
        try {
            $transport->start();
            return ['verified' => true, 'message' => 'SMTP 連線與認證通過, 沒有寄出信件；寄件者與收件者仍需實寄驗證'];
        } catch (\Throwable) { DomainError::fail('SMTP_ERROR', 'SMTP 連線或認證失敗, 請檢查伺服器設定'); }
        finally { try { $transport->stop(); } catch (\Throwable) {} }
    }
}
