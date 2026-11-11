<?php

namespace Tests\Feature;

use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** 有界讀取API的權限、投影、分頁及跨租戶契約。 */
class ScopedReadApiTest extends TestCase
{
    use RefreshDatabase;

    private function rpc(string $operation, array $input = [])
    {
        return $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    public function test_project_issue_reads_are_scoped_bounded_and_activity_is_opt_in(): void
    {
        $this->getJson('/api/v1/projects')->assertStatus(401);
        $actor = $this->postJson('/api/auth/register', ['name' => 'Query', 'email' => 'query@example.test', 'password' => 'test-local-password', 'workspaceName' => 'Queries'])->assertOk()->json('data');
        $project = $this->rpc('createProject', ['name' => 'First', 'code' => 'FIRST'])->assertOk()->json('data');
        $other = $this->rpc('createProject', ['name' => 'Other', 'code' => 'OTHER'])->assertOk()->json('data');
        $items = [];
        for ($i = 0; $i < 23; $i++) {
            $items[] = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Task '.$i, 'description' => $i === 0 ? 'literal % description' : 'Other'])->assertOk()->json('data');
        }
        $foreign = $this->rpc('createIssue', ['projectId' => $other['id'], 'title' => 'Excluded'])->assertOk()->json('data');
        $base = '/api/v1/projects/'.$project['id'];
        $list = $this->getJson($base.'/issues')->assertOk()->json('data');
        $this->assertCount(20, $list['items']);
        $this->assertSame(23, $list['total']);
        $second = $this->getJson($base.'/issues?page=2')->assertOk()->json('data');
        $this->assertCount(3, $second['items']);
        $this->assertSame([], array_intersect(array_column($list['items'], 'id'), array_column($second['items'], 'id')));
        foreach ($list['items'] as $item) {
            $this->assertSame($project['id'], $item['projectId']);
            foreach (['description', 'comments', 'history', 'legacy_attributes'] as $field) {
                $this->assertArrayNotHasKey($field, $item);
            }
        }
        $this->getJson($base.'/issues?identifier='.urlencode($items[0]['identifier']))->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.id', $items[0]['id']);
        $this->getJson($base.'/issues?perPage=101')->assertStatus(400);
        $this->getJson($base.'/issues?search=%25')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson($base.'/issues/'.$foreign['id'])->assertStatus(404);
        $this->getJson('/api/v1/projects/missing/issues')->assertStatus(404);
        $this->getJson($base.'/issues', ['X-Workspace-ID' => 'wrong-space'])->assertStatus(409);
        $this->getJson('/api/v1/projects?perPage=1')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.total', 2);
        $details = $this->getJson($base)->assertOk()->json('data');
        $this->assertContains('issue.read', $details['permissions']);
        $this->assertArrayHasKey('statuses', $details);
        $this->assertArrayNotHasKey('settingsHistory', $details);

        $store = (new RecordStore)->forWorkspace($actor['workspace']['id']);
        $issue = $items[0];
        $issue['comments'] = [];
        for ($i = 0; $i < 25; $i++) {
            $issue['comments'][] = ['id' => (string) Str::uuid(), 'content' => 'Comment '.$i, 'author' => 'Query', 'createdAt' => now()->toISOString()];
        }
        $store->put('issues', $issue['id'], $issue);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($base.'/issues')->assertOk();
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'issue_comments')));
        DB::flushQueryLog();
        $detail = $this->getJson($base.'/issues/'.$issue['id'])->assertOk()->json('data');
        $this->assertSame($issue['description'], $detail['description']);
        $this->assertArrayNotHasKey('comments', $detail);
        $this->assertArrayNotHasKey('history', $detail);
        $this->getJson($base.'/issues/'.$issue['id'].'/comments')->assertOk()->assertJsonCount(20, 'data.items')->assertJsonPath('data.total', 25);
        $this->getJson($base.'/issues/'.$issue['id'].'/comments?page=2')->assertOk()->assertJsonCount(5, 'data.items');
        $this->getJson($base.'/issues/'.$issue['id'].'/history')->assertOk()->assertJsonCount(1, 'data.items');
    }
}
