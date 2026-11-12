<?php

namespace Tests\Feature;

use App\Models\Issue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\StoredWriteResults;
use Tests\TestCase;

/** TT-178：卡片／工時寫入的專用Request與有界結果；簽章、授權順序與revision語意不變。 */
class WriteContractTest extends TestCase
{
    use RefreshDatabase, StoredWriteResults;

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
     * 卡片與工時寫入只回有界欄位；完整內容仍正確保存並可由GET讀取。
     *
     * @return void
     */
    public function test_issue_and_time_writes_return_bounded_results(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Writer', 'email' => 'writer-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Write'])->assertOk();
        $project = $this->rpc('createProject', ['name' => 'Write', 'code' => 'WRT'])->assertOk()->json('data');
        $issue = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Card', 'description' => str_repeat('長描述 ', 500)])->assertOk()->json('data');
        $this->assertSame(['id', 'projectId', 'identifier', 'revision'], array_keys($issue));
        $updated = $this->rpc('updateIssue', ['id' => $issue['id'], 'title' => 'Card 2', 'expectedRevision' => $issue['revision']])->assertOk()->json('data');
        $this->assertSame($issue['revision'] + 1, $updated['revision']);
        $commented = $this->rpc('addComment', ['id' => $issue['id'], 'content' => 'Hi', 'expectedRevision' => $updated['revision']])->assertOk()->json('data');
        $this->assertSame(['id', 'projectId', 'identifier', 'revision'], array_keys($commented));
        $this->assertSame('Hi', $this->storedIssue($commented)['comments'][0]['content']);
        $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'])->assertOk()->assertJsonPath('data.title', 'Card 2');

        $entry = $this->rpc('logTime', ['issueId' => $issue['id'], 'workDate' => '2026-10-05', 'minutes' => 30, 'note' => 'n'])->assertOk()->json('data');
        $this->assertSame(['id', 'projectId', 'issueId', 'workDate', 'minutes', 'revision'], array_keys($entry));
        $changed = $this->rpc('updateTime', ['id' => $entry['id'], 'issueId' => $issue['id'], 'workDate' => '2026-10-05', 'minutes' => 45, 'expectedRevision' => $entry['revision']])->assertOk()->json('data');
        $this->assertSame(45, $changed['minutes']);
        $voided = $this->rpc('voidTime', ['id' => $entry['id'], 'expectedRevision' => $changed['revision']])->assertOk()->json('data');
        $this->assertSame($changed['revision'] + 1, $voided['revision']);
        $this->assertLessThan(300, max(array_map('strlen', DB::table('operation_receipts')->where('result', 'not like', '%"statuses"%')->pluck('result')->all())), '卡片／工時receipt只保存有界結果');
    }

    /**
     * 型別錯誤回INVALID_INPUT；未知欄位保留在簽章內；不可見卡片缺欄位仍先回404。
     *
     * @return void
     */
    public function test_requests_check_types_keep_signature_and_authorize_first(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Typed', 'email' => 'typed-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Typed'])->assertOk();
        $project = $this->rpc('createProject', ['name' => 'Typed', 'code' => 'TYP'])->assertOk()->json('data');
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => ['not', 'text']])->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->rpc('logTime', ['issueId' => 'x', 'minutes' => 'many'])->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => str_repeat('長', 181)])->assertStatus(400)->assertJsonPath('error.message', '標題必填或超過字數限制');

        $input = ['projectId' => $project['id'], 'title' => 'Extra', 'clientHint' => 'keep', 'requestId' => 'extra-key'];
        $first = $this->rpc('createIssue', $input)->assertOk()->json('data');
        $this->assertSame($first, $this->rpc('createIssue', $input)->assertOk()->json('data'), '同輸入（含未知欄位）重播原結果');
        $this->rpc('createIssue', [...$input, 'clientHint' => 'changed'])->assertStatus(409)->assertJsonPath('error.code', 'REQUEST_CONFLICT');
        $this->assertSame(1, Issue::where('title', 'Extra')->count());

        $this->rpc('updateIssue', ['id' => (string) Str::uuid()])->assertStatus(404);
        $this->rpc('addComment', ['id' => (string) Str::uuid()])->assertStatus(404);
    }
}
