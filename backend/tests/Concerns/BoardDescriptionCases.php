<?php

namespace Tests\Concerns;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** TT-309：看板簡述的建立、更新、保留與讀取；SQLite與獨立MySQL共用。 */
trait BoardDescriptionCases
{
    /**
     * 經正式Session RPC送出並補requestId。
     *
     * @param  string  $operation  已註冊的RPC操作
     * @param  array<string,mixed>  $input  測試輸入
     * @return TestResponse 真實HTTP回應
     */
    private function descriptionRpc(string $operation, array $input = []): TestResponse
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /**
     * 建立／更新帶簡述；沒帶description時保留原值；超過300字擋下；列表、單看板與Default都回description。
     *
     * @return void
     */
    public function test_board_description_create_update_keep_and_read(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Describer', 'email' => 'describer-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Describe'])->assertOk();
        $project = $this->descriptionRpc('createProject', ['name' => 'Describe', 'code' => 'DSC'])->assertOk()->json('data');
        $issue = $this->descriptionRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Card'])->assertOk()->json('data');
        $base = '/api/v1/projects/'.$project['id'].'/boards';

        $board = $this->descriptionRpc('createBoard', ['projectId' => $project['id'], 'name' => '效能', 'description' => '  只收量測過的效能改善  ', 'selectedIssueIds' => [$issue['id']]])->assertOk()->json('data');
        $this->getJson($base.'/'.$board['id'])->assertOk()->assertJsonPath('data.description', '只收量測過的效能改善');
        $this->getJson($base)->assertOk()->assertJsonPath('data.items.0.description', '只收量測過的效能改善')->assertJsonPath('data.defaultBoard.description', '');

        $this->descriptionRpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision'], 'name' => '效能改進', 'description' => '收效能量測與改善', 'selectedIssueIds' => [$issue['id']]])->assertOk();
        $this->getJson($base.'/'.$board['id'])->assertOk()->assertJsonPath('data.name', '效能改進')->assertJsonPath('data.description', '收效能量測與改善');

        // 舊呼叫端（沒帶description）更新名稱時, 簡述不會被清空。
        $this->descriptionRpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision'] + 1, 'name' => '效能', 'selectedIssueIds' => [$issue['id']]])->assertOk();
        $this->getJson($base.'/'.$board['id'])->assertOk()->assertJsonPath('data.description', '收效能量測與改善');

        $this->descriptionRpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision'] + 2, 'name' => '效能', 'description' => str_repeat('字', 301), 'selectedIssueIds' => [$issue['id']]])->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->descriptionRpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision'] + 2, 'name' => '效能', 'description' => '', 'selectedIssueIds' => [$issue['id']]])->assertOk();
        $this->getJson($base.'/'.$board['id'])->assertOk()->assertJsonPath('data.description', '');
        $this->descriptionRpc('createBoard', ['projectId' => $project['id'], 'name' => '沒簡述'])->assertOk()->assertJsonPath('data.id', fn ($id) => is_string($id));
    }
}
