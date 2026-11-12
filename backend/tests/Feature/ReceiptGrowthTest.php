<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\MySqlTestCase;

/** 在隔離MySQL量測receipt容量，並鎖定不可因精簡而破壞的重播契約。 */
class ReceiptGrowthTest extends MySqlTestCase
{
    use DatabaseTransactions;

    /** 真實HTTP操作，每次預設建立獨立requestId，重播案例則明確指定。
     *
     * @param  string  $operation  API操作
     * @param  array  $input  輸入欄位
     * @return TestResponse
     */
    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /** 同一卡片持續留言／改標題時，逐checkpoint回報JSON邏輯bytes與MySQL binary JSON bytes。
     *
     * @return void
     */
    public function test_native_receipt_growth_and_replay_contract(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'Receipt', 'email' => Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Receipt'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'Receipt', 'code' => 'RCP'])->assertOk()->json('data');
        $create = ['projectId' => $project['id'], 'title' => 'Initial', 'description' => str_repeat('需求內容 ', 100), 'requestId' => 'receipt-profile-create'];
        $initial = $this->rpc('createIssue', $create)->assertOk()->json('data');
        $issue = $initial;
        $checkpoints = [$this->measureReceipts($identity['user']['id'], 0)];
        for ($step = 1; $step <= 64; $step++) {
            $issue = $this->rpc('updateIssue', ['id' => $issue['id'], 'expectedRevision' => $issue['revision'], 'title' => '調整 '.$step.' '.str_repeat('A', 50), 'requestId' => 'receipt-profile-edit-'.$step])->assertOk()->json('data');
            $issue = $this->rpc('addComment', ['id' => $issue['id'], 'expectedRevision' => $issue['revision'], 'content' => '第'.$step.'次 '.str_repeat('進度記錄 ', 40), 'requestId' => 'receipt-profile-comment-'.$step])->assertOk()->json('data');
            if (in_array($step, [8, 16, 32, 64], true)) {
                $checkpoints[] = $this->measureReceipts($identity['user']['id'], $step);
            }
        }
        $this->assertCount(64, $issue['comments']);
        $this->assertCount(65, $issue['history']);
        $this->assertSame(129, $issue['revision']);
        $last = end($checkpoints);
        $this->assertSame(129, $last['receiptCount']);
        $this->assertGreaterThan($checkpoints[3]['jsonStorageBytes'] * 3, $last['jsonStorageBytes'], '累積完整快照呈超線性成長');
        $beforeReplay = $last['jsonStorageBytes'];
        $this->assertDtoSame($initial, $this->rpc('createIssue', $create)->assertOk()->json('data'));
        $this->assertSame($beforeReplay, $this->measureReceipts($identity['user']['id'], 64)['jsonStorageBytes']);
        $this->assertSame(1, Issue::where('project_id', Issue::where('public_id', $issue['id'])->value('project_id'))->count());
        $this->rpc('createIssue', [...$create, 'title' => 'Different'])->assertStatus(409)->assertJsonPath('error.code', 'REQUEST_CONFLICT');
        $this->assertSame(129, Issue::where('public_id', $issue['id'])->value('revision'));
        $todo = $this->rpc('createTodo', ['content' => str_repeat('速記 ', 200), 'requestId' => 'receipt-todo-create'])->assertOk()->json('data');
        for ($step = 1; $step <= 32; $step++) {
            $todo = $this->rpc('updateTodo', ['id' => $todo['id'], 'expectedRevision' => $todo['revision'], 'content' => '第'.$step.'次 '.str_repeat('速記 ', 200), 'requestId' => 'receipt-todo-edit-'.$step])->assertOk()->json('data');
        }
        $todoStats = DB::table('todo_operation_receipts')->where('public_id', 'like', $identity['user']['id'].':receipt-todo-%')
            ->selectRaw('COUNT(*) as receipt_count, SUM(JSON_STORAGE_SIZE(result)) as storage_bytes, MIN(JSON_STORAGE_SIZE(result)) as min_bytes, MAX(JSON_STORAGE_SIZE(result)) as max_bytes')->first();
        $this->assertSame(33, (int) $todoStats->receipt_count);
        $this->assertLessThan(150, (int) $todoStats->max_bytes, 'Todo目前已回傳精簡id／revision，不能假設也保存全文');
        $model = Issue::where('public_id', $issue['id'])->firstOrFail();
        $compression = $this->compressionCandidate($identity['user']['id']);
        $auditBytes = (int) DB::table('audit_events')->where('entity_type', 'issues')->where('entity_id', $model->id)->sum(DB::raw('JSON_STORAGE_SIZE(event_data)'));
        fwrite(STDERR, 'TT227_METRICS '.json_encode(['driver' => DB::getDriverName(), 'checkpoints' => $checkpoints, 'currentIssueJsonBytes' => strlen(json_encode($issue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 'currentIssueAuditJsonStorageBytes' => $auditBytes, 'losslessCompressionCandidate' => $compression, 'todo' => ['receiptCount' => (int) $todoStats->receipt_count, 'jsonStorageBytes' => (int) $todoStats->storage_bytes, 'minReceiptBytes' => (int) $todoStats->min_bytes, 'maxReceiptBytes' => (int) $todoStats->max_bytes]], JSON_THROW_ON_ERROR).PHP_EOL);
        // 即使receipt仍在，當次Workspace資格失效也不得讀舊結果。
        WorkspaceMember::where('workspace_id', $model->workspace_id)->where('user_id', $identity['user']['id'])->update(['disabled_at' => now()]);
        $this->rpc('createIssue', $create)->assertStatus(401);
    }

    /** 只在隔離fixture量測逐筆gzip候選，不修改資料庫儲存格式或任何receipt。
     *
     * @param  string  $userId  本測例作者
     * @return array<string, int> 各筆獨立壓縮的payload bytes及本機CPU耗時
     */
    private function compressionCandidate(string $userId): array
    {
        $stats = ['receiptCount' => 0, 'jsonBytes' => 0, 'gzipBytes' => 0, 'encodeMicroseconds' => 0, 'decodeMicroseconds' => 0];
        foreach (DB::table('operation_receipts')->where('public_id', 'like', $userId.':receipt-profile-%')->select('result')->orderBy('id')->cursor() as $row) {
            $json = $row->result;
            $start = hrtime(true);
            $compressed = gzencode($json, 6);
            $stats['encodeMicroseconds'] += (int) ((hrtime(true) - $start) / 1000);
            $start = hrtime(true);
            $restored = gzdecode($compressed);
            $stats['decodeMicroseconds'] += (int) ((hrtime(true) - $start) / 1000);
            $this->assertSame($json, $restored, '候選編碼逐byte往返，不重建最新Model代替原結果');
            $stats['receiptCount']++;
            $stats['jsonBytes'] += strlen($json);
            $stats['gzipBytes'] += strlen($compressed);
        }

        return $stats;
    }

    /** 只統計本測例receipt，不使用INFORMATION_SCHEMA近似值當實際磁碟占用。
     *
     * @param  string  $userId  本測例作者
     * @param  int  $rounds  已執行的留言／改標題回合
     * @return array<string, int> 原始JSON與引擎JSON大小，不含索引及頁面額外成本
     */
    private function measureReceipts(string $userId, int $rounds): array
    {
        $row = DB::table('operation_receipts')->where('public_id', 'like', $userId.':receipt-profile-%')
            ->selectRaw('COUNT(*) as receipt_count, SUM(OCTET_LENGTH(CAST(result AS CHAR CHARACTER SET utf8mb4))) as json_text_bytes, SUM(JSON_STORAGE_SIZE(result)) as storage_bytes, MAX(JSON_STORAGE_SIZE(result)) as max_bytes')->first();

        return ['rounds' => $rounds, 'receiptCount' => (int) $row->receipt_count, 'jsonTextBytes' => (int) $row->json_text_bytes, 'jsonStorageBytes' => (int) $row->storage_bytes, 'maxReceiptBytes' => (int) $row->max_bytes];
    }
}
