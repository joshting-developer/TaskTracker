<?php

namespace App\Console\Commands;

use App\Domain\DomainError;
use App\Services\ReceiptCodec;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** 逐批有界轉換既有重播原結果：encode壓縮大結果、decode還原純JSON供回退到舊版程式。 */
class ReceiptCodecConvert extends Command
{
    protected $signature = 'tasktracker:receipt-codec {mode : encode或decode} {--batch=200 : 每個交易處理筆數} {--dry-run : 只統計不寫入}';

    protected $description = '轉換operation／todo receipt的原結果編碼；每筆轉換前後驗證無損，損壞資料保留不動';

    /**
     * 依id游標逐批鎖定、轉換並驗證；不刪除任何receipt。
     *
     * @param  ReceiptCodec  $codec  版本化無損編碼
     * @return int 結束碼（有無法讀取的資料時為1）
     */
    public function handle(ReceiptCodec $codec): int
    {
        $mode = $this->argument('mode');
        if (! in_array($mode, ['encode', 'decode'], true)) {
            $this->error('mode只能是encode或decode');

            return self::INVALID;
        }
        $batch = max(1, min(1000, (int) $this->option('batch')));
        $dry = (bool) $this->option('dry-run');
        $totals = ['scanned' => 0, 'changed' => 0, 'unreadable' => 0, 'bytesBefore' => 0, 'bytesAfter' => 0];
        foreach (['operation_receipts', 'todo_operation_receipts'] as $table) {
            $last = 0;
            do {
                $count = DB::transaction(function () use ($table, $batch, $codec, $mode, $dry, &$last, &$totals) {
                    $rows = DB::table($table)->where('id', '>', $last)->orderBy('id')->limit($batch)->lockForUpdate()->get(['id', 'result']);
                    foreach ($rows as $row) {
                        $last = $row->id;
                        $totals['scanned']++;
                        $text = (string) $row->result;
                        $stored = json_decode($text, true);
                        try {
                            $original = $codec->decode($stored);
                        } catch (DomainError) {
                            $totals['unreadable']++;

                            continue;
                        }
                        $target = $mode === 'encode' ? $codec->encode($original, true) : $original;
                        $next = json_encode($target, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                        $totals['bytesBefore'] += strlen($text);
                        $totals['bytesAfter'] += $target === $stored ? strlen($text) : strlen($next);
                        if ($target === $stored) {
                            continue;
                        }
                        // 寫入前再次還原比對，確保無損。
                        if ($codec->decode(json_decode($next, true)) !== $original) {
                            $totals['unreadable']++;

                            continue;
                        }
                        $totals['changed']++;
                        if (! $dry) {
                            DB::table($table)->where('id', $row->id)->update(['result' => $next]);
                        }
                    }

                    return $rows->count();
                });
            } while ($count === $batch);
        }
        $this->line(json_encode([$mode, 'dryRun' => $dry, ...$totals], JSON_UNESCAPED_UNICODE));

        return $totals['unreadable'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
