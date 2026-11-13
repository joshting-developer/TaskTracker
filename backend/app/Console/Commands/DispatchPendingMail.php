<?php

namespace App\Console\Commands;

use App\Services\MailDeliveryService;
use Illuminate\Console\Command;

/** broker中斷恢復後有界重派pending信件, 不直接寄SMTP */
class DispatchPendingMail extends Command
{
    protected $signature = 'mail:dispatch';

    protected $description = 'Dispatch up to 100 due mail outbox records';

    /** 框架注入outbox協調服務
     * @param  MailDeliveryService  $mail  有界重派
     * @return int 命令退出碼
     */
    public function handle(MailDeliveryService $mail): int
    {
        $this->info('Dispatched: '.$mail->dispatchPending());

        return self::SUCCESS;
    }
}
