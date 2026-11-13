<?php

namespace Tests\Concerns;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** TT-283：看板封存清單、唯讀查閱與還原；SQLite與獨立MySQL共用。 */
trait BoardArchiveCases
{
    /**
     * 經正式Session RPC送出並補requestId。
     *
     * @param  string  $operation  已註冊的RPC操作
     * @param  array<string,mixed>  $input  測試輸入
     * @return TestResponse 真實HTTP回應
     */
    private function boardRpc(string $operation, array $input = []): TestResponse
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /**
     * 建立擁有者、專案、兩張卡和一個收錄其中一張的看板。
     *
     * @return array{0: array<string,mixed>, 1: array<string,mixed>, 2: array<string,mixed>} 專案、看板、被收錄的卡
     */
    private function archiveFixture(): array
    {
        $this->postJson('/api/auth/register', ['name' => 'Archiver', 'email' => 'archiver-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Archive'])->assertOk();
        $project = $this->boardRpc('createProject', ['name' => 'Archive', 'code' => 'ARC'])->assertOk()->json('data');
        $issue = $this->boardRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Kept card'])->assertOk()->json('data');
        $this->boardRpc('createIssue', ['projectId' => $project['id'], 'title' => 'Other card'])->assertOk();
        $board = $this->boardRpc('createBoard', ['projectId' => $project['id'], 'name' => 'Sprint 1', 'selectedIssueIds' => [$issue['id']]])->assertOk()->json('data');

        return [$project, $board, $issue];
    }

    /**
     * 封存後從使用中清單消失、出現在已封存清單；仍可唯讀查看與讀卡；寫入被擋；卡片沒被封存。
     *
     * @return void
     */
    public function test_archived_board_is_listed_readable_and_read_only(): void
    {
        [$project, $board, $issue] = $this->archiveFixture();
        $base = '/api/v1/projects/'.$project['id'].'/boards';
        $this->boardRpc('archiveBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision']])->assertOk()->assertJsonPath('data.revision', $board['revision'] + 1);

        $this->getJson($base)->assertOk()->assertJsonCount(0, 'data.items');
        $archived = $this->getJson($base.'?archived=1')->assertOk()->json('data');
        $this->assertSame([$board['id']], array_column($archived['items'], 'id'));
        $this->assertNotNull($archived['items'][0]['archivedAt']);
        $this->assertSame(1, $archived['items'][0]['issueCount']);
        $this->getJson($base.'?archived=maybe')->assertStatus(400);

        $show = $this->getJson($base.'/'.$board['id'])->assertOk()->json('data');
        $this->assertNotNull($show['archivedAt']);
        $this->assertSame([$issue['id']], $show['selectedIssueIds']);
        $this->getJson($base.'/'.$board['id'].'/issues')->assertOk()->assertJsonPath('data.items.0.id', $issue['id']);
        $this->getJson($base.'/'.$board['id'].'/version')->assertOk();
        $this->assertNull($this->getJson($base.'/default')->assertOk()->json('data.archivedAt'));

        $this->boardRpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision'] + 1, 'name' => 'Renamed'])->assertStatus(404);
        $this->boardRpc('archiveBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision'] + 1])->assertStatus(404);
        $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'])->assertOk();
    }

    /**
     * 還原需board.manage、正確revision；同requestId重播回原結果；還原後回到使用中清單且收錄不變；Default與未封存不可還原。
     *
     * @return void
     */
    public function test_restore_board_returns_it_with_same_inclusion(): void
    {
        [$project, $board, $issue] = $this->archiveFixture();
        $base = '/api/v1/projects/'.$project['id'].'/boards';
        $this->boardRpc('restoreBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision']])->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->boardRpc('restoreBoard', ['id' => 'default', 'expectedRevision' => 0])->assertStatus(400);
        $this->boardRpc('archiveBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision']])->assertOk();

        $this->boardRpc('restoreBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision']])->assertStatus(409)->assertJsonPath('error.code', 'REVISION_CONFLICT');
        $input = ['id' => $board['id'], 'expectedRevision' => $board['revision'] + 1, 'requestId' => (string) Str::uuid()];
        $result = $this->postJson('/api/rpc/restoreBoard', $input)->assertOk()->json('data');
        $this->assertSame([$board['id'], $project['id'], $board['revision'] + 2], [$result['id'], $result['projectId'], $result['revision']]);
        $this->assertEquals($result, $this->postJson('/api/rpc/restoreBoard', $input)->assertOk()->json('data'));

        $this->getJson($base.'?archived=1')->assertOk()->assertJsonCount(0, 'data.items');
        $active = $this->getJson($base)->assertOk()->json('data.items');
        $this->assertSame([$board['id']], array_column($active, 'id'));
        $this->assertNull($active[0]['archivedAt']);
        $this->getJson($base.'/'.$board['id'])->assertOk()->assertJsonPath('data.selectedIssueIds', [$issue['id']])->assertJsonPath('data.archivedAt', null);
        $this->boardRpc('updateBoard', ['id' => $board['id'], 'expectedRevision' => $board['revision'] + 2, 'name' => 'Sprint 1b', 'selectedIssueIds' => [$issue['id']]])->assertOk();
    }
}
