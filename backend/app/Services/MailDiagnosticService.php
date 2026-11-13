<?php

namespace App\Services;

use App\Data\MailDeliveryData;
use App\Domain\DomainError;
use App\Repositories\AccountRepository;
use App\Repositories\MailDeliveryRepository;
use Illuminate\Support\Str;

/** 明確操作的本機測試信與管理員狀態查閱, 不提供公開任意寄信API */
class MailDiagnosticService
{
    /** 注入最小帳號、信件資料入口與寄送用例
     * @param  AccountRepository  $accounts  帳號識別
     * @param  MailDeliveryRepository  $deliveries  最小狀態欄位
     * @param  MailDeliveryService  $mail  信件outbox
     */
    public function __construct(private AccountRepository $accounts, private MailDeliveryRepository $deliveries, private MailDeliveryService $mail) {}

    /** 只在本機／測試環境建立明確收件人的固定測試信
     * @param  string  $email  已授權收件帳號
     * @param  string  $key  本次測試的穩定UUID
     * @return string outbox UUID
     */
    public function queue(string $email, string $key): string
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true) || ! Str::isUuid($key)) {
            DomainError::fail('INVALID_INPUT', '測試寄信只允許本機環境與有效識別碼');
        }
        $user = $this->accounts->byEmail(mb_strtolower(trim($email))) ?? DomainError::fail('NOT_FOUND', '找不到收件帳號');
        $row = $this->mail->enqueue(new MailDeliveryData($user->id, 'diagnostic:'.$key, 'diagnostic', 'TaskTracker 郵件測試', "TaskTracker 背景寄信測試\n\n這封信由背景Queue寄出, 用於確認SMTP設定\n", now()->addHour()));

        return $row->id;
    }

    /** CLI只讀最小狀態, 不回地址、內容或憑證
     * @param  string  $id  信件UUID
     * @return array<string,mixed> 安全狀態欄位
     */
    public function status(string $id): array
    {
        $row = $this->deliveries->state($id) ?? DomainError::fail('NOT_FOUND', '找不到寄送紀錄');

        return ['id' => $row->id, 'status' => $row->status, 'attempts' => $row->attempts, 'errorCode' => $row->error_code, 'sentAt' => $row->sent_at?->toISOString()];
    }
}
