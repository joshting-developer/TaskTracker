<?php

namespace App\Jobs;

use App\Events\MailDeliveryFailed;
use App\Services\MailDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Event;
use Throwable;

/** Queue只序列化outbox ID, 不保存SMTP憑證、驗證碼或租戶物件 */
class SendTransactionalMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** 保存唯一事件ID
     * @param  string  $id  outbox UUID
     */
    public function __construct(public string $id) {}

    /** 框架執行期注入服務, 不將其序列化到Queue
     * @param  MailDeliveryService  $deliveries  狀態機
     * @return void
     */
    public function handle(MailDeliveryService $deliveries): void
    {
        $deliveries->deliver($this->id);
    }

    /** 只重試尚未交付的連線失敗
     * @return list<int> 延遲秒數
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    /** 失敗事件不包含原SMTP例外或信件內容
     * @param  ?Throwable  $error  框架失敗原因, 不另輸出
     * @return void
     */
    public function failed(?Throwable $error): void
    {
        Event::dispatch(new MailDeliveryFailed($this->id));
    }
}
