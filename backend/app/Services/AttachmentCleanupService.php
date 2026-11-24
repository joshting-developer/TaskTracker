<?php

namespace App\Services;

use App\Repositories\AttachmentRepository;
use App\Repositories\TodoAttachmentRepository;
use Closure;
use Illuminate\Support\Facades\DB;

/** 以有界候選清理過期物件, 先鎖定狀態再做交易外IO */
class AttachmentCleanupService
{
    /** 注入metadata與物件IO
     * @param  AttachmentRepository  $attachments  有界候選
     * @param  AttachmentStorage  $storage  可重試刪除
     * @param  TodoAttachmentRepository  $todoAttachments  紙條附件有界候選
     */
    public function __construct(private AttachmentRepository $attachments, private AttachmentStorage $storage, private TodoAttachmentRepository $todoAttachments) {}

    /** 每次最多1000筆, 失敗保留purging供下一次重試；TT-294起含紙條附件
     * @return array{purged:int,failed:int} 實際清除結果
     */
    public function run(): array
    {
        $result = ['purged' => 0, 'failed' => 0, 'seen' => 0];
        $this->sweep($result, fn (int $after) => $this->attachments->expired($after), fn (int $id) => $this->attachments->expiredLocked($id), fn ($row, array $values) => $this->attachments->save($row, $values));
        $this->sweep($result, fn (int $after) => $this->todoAttachments->expired($after), fn (int $id) => $this->todoAttachments->expiredLocked($id), fn ($row, array $values) => $this->todoAttachments->save($row, $values));

        return ['purged' => $result['purged'], 'failed' => $result['failed']];
    }

    /** 單一來源的有界清理, 與其他來源共用每次上限
     * @param  array{purged:int,failed:int,seen:int}  $result  累計結果
     * @param  Closure(int):array  $expired  逾期候選
     * @param  Closure(int):mixed  $locked  鎖定重讀
     * @param  Closure(mixed,array):mixed  $save  保存狀態
     * @return void
     */
    private function sweep(array &$result, Closure $expired, Closure $locked, Closure $save): void
    {
        $after = 0;
        while ($result['seen'] < 1000 && $rows = $expired($after)) {
            foreach ($rows as $candidate) {
                $after = $candidate->id;
                $result['seen']++;
                $row = DB::transaction(function () use ($candidate, $locked, $save) {
                    $row = $locked($candidate->id);

                    return $row ? $save($row, ['status' => 'purging']) : null;
                });
                if (! $row) {
                    continue;
                }
                if (! $this->storage->delete($row->storage_disk, $row->storage_key)) {
                    $result['failed']++;

                    continue;
                }
                DB::transaction(function () use ($row, $locked, $save) {
                    $latest = $locked($row->id);
                    if ($latest) {
                        $save($latest, ['status' => 'purged', 'storage_key' => '', 'expires_at' => null]);
                    }
                });
                $result['purged']++;
            }
        }
    }
}
