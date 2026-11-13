<?php

namespace App\Console\Commands;

use App\Services\MailDiagnosticService;
use Illuminate\Console\Command;

/** 管理員只查指定outbox狀態, 不印信件內容與地址 */
class MailStatus extends Command
{
    protected $signature = 'mail:status {id}';

    protected $description = 'Read safe delivery state for one outbox ID';

    /** 框架注入狀態查閱服務
     * @param  MailDiagnosticService  $mail  最小狀態用例
     * @return int 命令退出碼
     */
    public function handle(MailDiagnosticService $mail): int
    {
        $this->line(json_encode($mail->status((string) $this->argument('id')), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
