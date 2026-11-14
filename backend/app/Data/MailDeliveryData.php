<?php

namespace App\Data;

use DateTimeInterface;

/** 可信業務用例產生的交易信件, 不接收任意HTTP信件內容 */
final readonly class MailDeliveryData
{
    /** 保存本次收件與有期限內容
     *
     * @param  string  $userId  可信帳號, 邀請時為邀請者
     * @param  string  $eventKey  同一業務事件的穩定去重鍵
     * @param  string  $kind  diagnostic、verification或invitation
     * @param  string  $subject  固定範本標題
     * @param  string  $body  純文字範本內容, 入庫前加密
     * @param  DateTimeInterface  $expiresAt  寄送截止時間
     * @param  ?string  $verificationId  當次驗證ID
     * @param  ?int  $invitationId  當次邀請內部鍵, 僅invitation使用
     */
    public function __construct(public string $userId, public string $eventKey, public string $kind, public string $subject, public string $body, public DateTimeInterface $expiresAt, public ?string $verificationId = null, public ?int $invitationId = null) {}
}
