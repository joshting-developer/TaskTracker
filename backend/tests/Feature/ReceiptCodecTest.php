<?php

namespace Tests\Feature;

use App\Domain\DomainError;
use App\Models\Issue;
use App\Services\ReceiptCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** TT-255：重播原結果的版本化無損編碼、雙讀、損壞拒絕與批次轉換／回退。 */
class ReceiptCodecTest extends TestCase
{
    use RefreshDatabase;

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
     * 讀出某requestId的receipt原始欄位內容。
     *
     * @param  string  $userId  操作者
     * @param  string  $requestId  操作識別碼
     * @return array<mixed> 欄位JSON
     */
    private function stored(string $userId, string $requestId): array
    {
        return json_decode(DB::table('operation_receipts')->where('public_id', $userId.':'.$requestId)->value('result'), true);
    }

    /**
     * 小結果不編碼、大結果無損往返；未知codec、損壞內容與checksum不符明確失敗。
     *
     * @return void
     */
    public function test_codec_is_lossless_threshold_based_and_rejects_corruption(): void
    {
        $codec = new ReceiptCodec('gzip-v1', 1024);
        $small = ['id' => 'x', 'revision' => 1];
        $this->assertSame($small, $codec->encode($small), '小結果原樣保存');
        $large = ['id' => 'x', 'title' => '中文 "quote" \\ / '."\u{1F600}", 'nested' => ['list' => range(1, 50), 'map' => ['b' => 2, 'a' => 1]], 'body' => str_repeat('需求內容 ', 400), 'zero' => 0, 'flag' => false, 'none' => null];
        $encoded = $codec->encode($large);
        $this->assertSame('gzip-v1', $encoded[ReceiptCodec::MARKER]);
        $this->assertLessThan(strlen(json_encode($large)) * 0.8, strlen(json_encode($encoded)));
        $this->assertSame($large, $codec->decode($encoded), '無損往返');
        $this->assertSame($large, $codec->decode(json_decode(json_encode($encoded), true)), '經JSON欄位保存後仍無損');
        $this->assertSame($large, (new ReceiptCodec('json', 1024))->encode($large), '未啟用時不編碼');
        $this->assertSame('gzip-v1', (new ReceiptCodec('json', 1024))->encode($large, true)[ReceiptCodec::MARKER], '批次轉換可強制編碼');
        $this->assertSame($large, (new ReceiptCodec('json', 1024))->decode($encoded), '未啟用寫入時仍可讀envelope');
        $random = ['data' => base64_encode(random_bytes(4000))];
        $this->assertSame($random, $codec->encode($random), '壓縮收益不足時不改格式');

        foreach ([
            [...$encoded, ReceiptCodec::MARKER => 'zstd-v9'],
            [...$encoded, 'data' => 'not base64 !!'],
            [...$encoded, 'data' => base64_encode('broken')],
            [...$encoded, 'sha256' => str_repeat('0', 64)],
            [...$encoded, 'bytes' => 1],
        ] as $broken) {
            try {
                $codec->decode($broken);
                $this->fail('損壞或未知codec必須失敗');
            } catch (DomainError $error) {
                $this->assertSame('RECEIPT_UNREADABLE', $error->errorCode);
            }
        }
    }

    /**
     * 啟用後同key同結果、改payload為409、舊純JSON receipt仍可重播；損壞的receipt不重新執行。
     *
     * @return void
     */
    public function test_replay_keeps_contract_with_encoded_and_plain_receipts(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Codec', 'email' => 'codec-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Codec'])->assertOk()->json('data');
        $userId = $identity['user']['id'];
        $project = $this->rpc('createProject', ['name' => 'Codec', 'code' => 'CDC'])->assertOk()->json('data');
        $plainInput = ['projectId' => $project['id'], 'title' => 'Plain', 'description' => str_repeat('舊格式 ', 300), 'requestId' => 'codec-plain'];
        $plain = $this->rpc('createIssue', $plainInput)->assertOk()->json('data');
        $this->assertArrayNotHasKey(ReceiptCodec::MARKER, $this->stored($userId, 'codec-plain'), '預設json不編碼');

        config(['tasktracker.receipts.codec' => 'gzip-v1', 'tasktracker.receipts.min_bytes' => 1024]);
        $input = ['projectId' => $project['id'], 'title' => 'Encoded', 'description' => str_repeat('需求內容 ', 300), 'requestId' => 'codec-encoded'];
        $first = $this->rpc('createIssue', $input)->assertOk()->json('data');
        $this->assertSame('gzip-v1', $this->stored($userId, 'codec-encoded')[ReceiptCodec::MARKER]);
        $count = Issue::count();
        $this->assertSame($first, $this->rpc('createIssue', $input)->assertOk()->json('data'), '同key回原結果');
        $this->assertSame($plain, $this->rpc('createIssue', $plainInput)->assertOk()->json('data'), '舊純JSON receipt雙讀');
        $this->rpc('createIssue', [...$input, 'title' => 'Changed'])->assertStatus(409)->assertJsonPath('error.code', 'REQUEST_CONFLICT');
        $this->assertSame($count, Issue::count(), '重播與衝突不新增卡片');
        $small = $this->rpc('createTodo', ['content' => '短', 'requestId' => 'codec-todo'])->assertOk()->json('data');
        $this->assertArrayNotHasKey(ReceiptCodec::MARKER, json_decode(DB::table('todo_operation_receipts')->where('public_id', $userId.':codec-todo')->value('result'), true), '小結果不編碼');
        $this->assertSame($small, $this->rpc('createTodo', ['content' => '短', 'requestId' => 'codec-todo'])->assertOk()->json('data'));

        $broken = [...$this->stored($userId, 'codec-encoded'), 'data' => base64_encode('broken')];
        DB::table('operation_receipts')->where('public_id', $userId.':codec-encoded')->update(['result' => json_encode($broken)]);
        $response = $this->rpc('createIssue', $input);
        $this->assertSame('RECEIPT_UNREADABLE', $response->json('error.code'));
        $this->assertSame($count, Issue::count(), '損壞receipt不重新執行');
        $this->assertNotNull(DB::table('operation_receipts')->where('public_id', $userId.':codec-encoded')->value('id'), '保留key');
    }

    /**
     * encode批次轉換後重播不變；decode還原為純JSON供回退舊版；損壞資料保留並回報。
     *
     * @return void
     */
    public function test_batch_conversion_is_bounded_lossless_and_reversible(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Batch', 'email' => 'batch-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Batch'])->assertOk()->json('data');
        $userId = $identity['user']['id'];
        $project = $this->rpc('createProject', ['name' => 'Batch', 'code' => 'BAT'])->assertOk()->json('data');
        $inputs = [];
        $results = [];
        for ($n = 0; $n < 7; $n++) {
            $inputs[$n] = ['projectId' => $project['id'], 'title' => 'Card '.$n, 'description' => str_repeat('批次 ', 600), 'requestId' => 'batch-'.$n];
            $results[$n] = $this->rpc('createIssue', $inputs[$n])->assertOk()->json('data');
        }
        $plainRows = DB::table('operation_receipts')->orderBy('id')->pluck('result', 'id')->map(fn ($text) => json_decode($text, true))->all();
        config(['tasktracker.receipts.min_bytes' => 1024]);

        $this->assertSame(0, Artisan::call('tasktracker:receipt-codec', ['mode' => 'encode', '--batch' => 3, '--dry-run' => true]));
        $this->assertArrayNotHasKey(ReceiptCodec::MARKER, $this->stored($userId, 'batch-0'), 'dry-run不寫入');
        $this->assertSame(0, Artisan::call('tasktracker:receipt-codec', ['mode' => 'encode', '--batch' => 3]));
        for ($n = 0; $n < 7; $n++) {
            $this->assertSame('gzip-v1', $this->stored($userId, 'batch-'.$n)[ReceiptCodec::MARKER]);
            $this->assertSame($results[$n], $this->rpc('createIssue', $inputs[$n])->assertOk()->json('data'), '轉換後重播原結果');
        }
        $this->assertSame(0, Artisan::call('tasktracker:receipt-codec', ['mode' => 'encode', '--batch' => 3]), '重跑冪等');

        $this->assertSame(0, Artisan::call('tasktracker:receipt-codec', ['mode' => 'decode', '--batch' => 2]));
        $this->assertSame($plainRows, DB::table('operation_receipts')->orderBy('id')->pluck('result', 'id')->map(fn ($text) => json_decode($text, true))->all(), 'decode還原為原本純JSON');

        Artisan::call('tasktracker:receipt-codec', ['mode' => 'encode']);
        $broken = [...$this->stored($userId, 'batch-3'), 'sha256' => str_repeat('0', 64)];
        DB::table('operation_receipts')->where('public_id', $userId.':batch-3')->update(['result' => json_encode($broken)]);
        $this->assertSame(1, Artisan::call('tasktracker:receipt-codec', ['mode' => 'decode']), '有損壞資料時回報失敗');
        $this->assertSame($broken, $this->stored($userId, 'batch-3'), '損壞資料保留不動');
        $this->assertArrayNotHasKey(ReceiptCodec::MARKER, $this->stored($userId, 'batch-4'), '其他資料照常還原');
    }
}
