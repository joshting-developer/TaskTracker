<?php

namespace App\Console\Commands;

use App\Services\AttachmentCleanupService;
use Illuminate\Console\Command;

/** 清理過期pending與刪除保留到期的附件 */
class CleanupAttachments extends Command
{
    protected $signature = 'attachments:cleanup';

    protected $description = 'Purge expired private attachments in bounded batches';

    /** Artisan生命週期由框架注入服務
     * @param  AttachmentCleanupService  $cleanup  清理用例
     * @return int 命令退出碼
     */
    public function handle(AttachmentCleanupService $cleanup): int
    {
        $result = $cleanup->run();
        $this->info('Purged: '.$result['purged'].'; failed: '.$result['failed']);

        return $result['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
