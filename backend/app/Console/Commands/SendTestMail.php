<?php

namespace App\Console\Commands;

use App\Services\MailDiagnosticService;
use Illuminate\Console\Command;

/** 只有明確執行命令才建立測試信, 不在verify或啟動時寄送 */
class SendTestMail extends Command
{
    protected $signature = 'mail:test {--email=} {--key=}';

    protected $description = 'Queue one explicitly authorized local test email';

    /** Artisan框架注入服務
     * @param  MailDiagnosticService  $mail  固定測試信用例
     * @return int 命令退出碼
     */
    public function handle(MailDiagnosticService $mail): int
    {
        $this->info('Queued mail: '.$mail->queue((string) $this->option('email'), (string) $this->option('key')));

        return self::SUCCESS;
    }
}
