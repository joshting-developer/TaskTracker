<?php

namespace App\Services;

use App\Repositories\AttachmentRepository;
use Illuminate\Support\Facades\DB;

/** 以有界候選清理過期物件, 先鎖定狀態再做交易外IO */
class AttachmentCleanupService
{
    /** 注入metadata與物件IO
     * @param  AttachmentRepository  $attachments  有界候選
     * @param  AttachmentStorage  $storage  可重試刪除
     */
    public function __construct(private AttachmentRepository $attachments, private AttachmentStorage $storage) {}

    /** 每次最多1000筆, 失敗保留purging供下一次重試
     * @return array{purged:int,failed:int} 實際清除結果
     */
    public function run(): array
    {
        $after = 0;
        $purged = 0;
        $failed = 0;
        $seen = 0;
        while ($seen < 1000 && $rows = $this->attachments->expired($after)) {
            foreach ($rows as $candidate) {
                $after = $candidate->id;
                $seen++;
                $row = DB::transaction(function () use ($candidate) {
                    $row = $this->attachments->expiredLocked($candidate->id);

                    return $row ? $this->attachments->save($row, ['status' => 'purging']) : null;
                });
                if (! $row) {
                    continue;
                }
                if (! $this->storage->delete($row->storage_disk, $row->storage_key)) {
                    $failed++;

                    continue;
                }
                DB::transaction(function () use ($row) {
                    $latest = $this->attachments->expiredLocked($row->id);
                    if ($latest) {
                        $this->attachments->save($latest, ['status' => 'purged', 'storage_key' => '', 'expires_at' => null]);
                    }
                });
                $purged++;
            }
        }

        return compact('purged', 'failed');
    }
}
