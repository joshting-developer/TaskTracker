<?php

namespace App\Listeners;

use App\Events\MailDeliveryFailed;
use App\Services\MailDeliveryService;

/** Queue失敗回呼經注入服務更新狀態, 不從Job使用Service Locator */
class RecordMailDeliveryFailure
{
    /** 注入執行期狀態服務
     * @param  MailDeliveryService  $deliveries  寄送狀態機
     */
    public function __construct(private MailDeliveryService $deliveries) {}

    /** 接收框架事件輸入
     * @param  MailDeliveryFailed  $event  信件ID
     * @return void
     */
    public function handle(MailDeliveryFailed $event): void
    {
        $this->deliveries->failed($event->id);
    }
}
