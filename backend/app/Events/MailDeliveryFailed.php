<?php

namespace App\Events;

/** Queue失敗只傳事件ID, 不傳收件人或信件內容 */
final readonly class MailDeliveryFailed
{
    /** 保存信件UUID
     * @param  string  $id  outbox公開UUID
     */
    public function __construct(public string $id) {}
}
