<?php

namespace Tests\Feature;

use App\Services\ReceiptCodec;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\MySqlTestCase;

/** TT-255：隔離MySQL量測gzip-v1對receipt儲存、編解碼CPU與程序內重播延遲的影響。 */
class ReceiptCodecMySqlTest extends MySqlTestCase
{
    use DatabaseTransactions;

    /**
     * 透過正式HTTP寫入建立人造測試資料。
     *
     * @param  string  $operation  RPC名稱
     * @param  array<string,mixed>  $input  測試輸入
     * @return TestResponse HTTP測試回應
     */
    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /**
     * 本人receipt的MySQL binary JSON合計。
     *
     * @param  string  $userId  操作者
     * @return array{rows:int,storage:int,text:int} 統計
     */
    private function storage(string $userId): array
    {
        $row = DB::table('operation_receipts')->where('public_id', 'like', $userId.':codec-%')
            ->selectRaw('COUNT(*) AS n, SUM(JSON_STORAGE_SIZE(result)) AS storage, SUM(OCTET_LENGTH(CAST(result AS CHAR CHARACTER SET utf8mb4))) AS text')->first();

        return ['rows' => (int) $row->n, 'storage' => (int) $row->storage, 'text' => (int) $row->text];
    }

    /**
     * 多次重播取中位數微秒。
     *
     * @param  array<string,mixed>  $input  原輸入
     * @return int 中位數微秒
     */
    private function replayMicros(array $input): int
    {
        $times = [];
        for ($n = 0; $n < 15; $n++) {
            $start = hrtime(true);
            $this->rpc('updateIssue', $input)->assertOk();
            $times[] = intdiv(hrtime(true) - $start, 1000);
        }
        sort($times);

        return $times[7];
    }

    /**
     * 64輪改標題＋留言後，比較純JSON與批次編碼後的儲存與重播；重播結果必須逐字相同。
     *
     * @return void
     */
    public function test_gzip_receipts_reduce_mysql_storage_without_changing_replay(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Codec', 'email' => Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Codec'])->assertOk()->json('data');
        $userId = $identity['user']['id'];
        $project = $this->rpc('createProject', ['name' => 'Codec', 'code' => 'CDM'])->assertOk()->json('data');
        $issue = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Initial', 'description' => str_repeat('需求內容 ', 100), 'requestId' => 'codec-create'])->assertOk()->json('data');
        $replayInput = null;
        for ($step = 1; $step <= 64; $step++) {
            $edit = ['id' => $issue['id'], 'expectedRevision' => $issue['revision'], 'title' => '調整 '.$step.' '.str_repeat('A', 50), 'requestId' => 'codec-edit-'.$step];
            $issue = $this->rpc('updateIssue', $edit)->assertOk()->json('data');
            $replayInput = $edit;
            $issue = $this->rpc('addComment', ['id' => $issue['id'], 'expectedRevision' => $issue['revision'], 'content' => '第'.$step.'次 '.str_repeat('進度記錄 ', 40), 'requestId' => 'codec-comment-'.$step])->assertOk()->json('data');
        }
        $original = $this->rpc('updateIssue', $replayInput)->assertOk()->json('data');
        $plain = $this->storage($userId);
        $plainReplay = $this->replayMicros($replayInput);

        $codec = new ReceiptCodec('gzip-v1', 4096);
        $results = DB::table('operation_receipts')->where('public_id', 'like', $userId.':codec-%')->pluck('result')->map(fn ($text) => json_decode($text, true))->all();
        $encodeStart = hrtime(true);
        $encoded = array_map(fn ($result) => $codec->encode($result), $results);
        $encodeMicros = intdiv(hrtime(true) - $encodeStart, 1000);
        $decodeStart = hrtime(true);
        $decoded = array_map(fn ($result) => $codec->decode($result), $encoded);
        $decodeMicros = intdiv(hrtime(true) - $decodeStart, 1000);
        $this->assertSame($results, $decoded, '全部無損往返');

        config(['tasktracker.receipts.min_bytes' => 4096]);
        $this->assertSame(0, Artisan::call('tasktracker:receipt-codec', ['mode' => 'encode', '--batch' => 50]));
        $after = $this->storage($userId);
        $encodedRows = collect($encoded)->filter(fn ($row) => isset($row[ReceiptCodec::MARKER]))->count();
        $this->assertSame($original, $this->rpc('updateIssue', $replayInput)->assertOk()->json('data'), '編碼後重播逐字相同');
        $encodedReplay = $this->replayMicros($replayInput);
        $this->assertLessThan($plain['storage'] * 0.3, $after['storage'], '大型receipt儲存至少省七成');

        $this->assertSame(0, Artisan::call('tasktracker:receipt-codec', ['mode' => 'decode', '--batch' => 50]));
        $this->assertSame($plain['storage'], $this->storage($userId)['storage'], 'decode回退後容量回到純JSON');

        fwrite(STDERR, 'receipt codec '.json_encode(['rows' => $plain['rows'], 'encodedRows' => $encodedRows, 'plainStorage' => $plain['storage'], 'encodedStorage' => $after['storage'],
            'plainText' => $plain['text'], 'encodedText' => $after['text'], 'encodeMicrosAll' => $encodeMicros, 'decodeMicrosAll' => $decodeMicros,
            'replayMedianMicrosPlain' => $plainReplay, 'replayMedianMicrosEncoded' => $encodedReplay]).PHP_EOL);
    }
}
