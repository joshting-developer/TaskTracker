<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\RecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_non_destructive_preserves_ids_and_upgrades_node_password(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tasktracker-import-');
        try {
            $source = new PDO('sqlite:'.$file);
            $source->exec('CREATE TABLE records(kind TEXT,id TEXT,payload TEXT,PRIMARY KEY(kind,id))');
            $insert = $source->prepare('INSERT INTO records VALUES(?,?,?)');
            // Node.js scryptSync 的固定測試向量, 不是真實帳號
            $account = ['id' => 'legacy-user', 'name' => 'Josh', 'email' => 'legacy@example.test', 'salt' => '0123456789abcdef0123456789abcdef', 'passwordHash' => '78643c1e79f18aecfa7ecbbd5dbaa5e0dd00c69cc06901b5b39883449316e10db1b62fdc126e7b8b7fa779ec9c3fe6d45e009ec7df1cef90fb495b24725ad7ce'];
            foreach ([['accounts', $account['id'], $account], ['workspaces', 'legacy-workspace', ['id' => 'legacy-workspace', 'name' => '舊工作空間']], ['w:legacy-workspace:members', $account['id'], ['id' => $account['id'], 'name' => $account['name'], 'email' => $account['email'], 'role' => 'admin']], ['w:legacy-workspace:projects', 'legacy-project', ['id' => 'legacy-project', 'name' => '舊專案', 'code' => 'OLD', 'revision' => 3]], ['sessions', 'old-token', ['id' => 'old-token']], ['w:legacy-workspace:requests', 'old-request', ['id' => 'old-request']]] as [$kind, $id, $payload]) $insert->execute([$kind, $id, json_encode($payload)]);
            $base = ['projectId' => 'legacy-project', 'title' => '舊任務', 'state' => 'todo', 'type' => 'task', 'epicId' => '', 'revision' => 3, 'updatedAt' => '2026-01-01T00:00:00Z', 'history' => [['kind' => 'created', 'actor' => '原作者', 'changes' => []]]];
            foreach ([['old-a', []], ['old-b', ['old-a']]] as [$id, $blockedBy]) $insert->execute(['w:legacy-workspace:issues', $id, json_encode([...$base, 'id' => $id, 'type' => $id === 'old-b' ? 'feature' : 'task', 'blockedBy' => $blockedBy])]);
            $before = hash_file('sha256', $file);
            $this->artisan('tasktracker:import-legacy', ['file' => $file])->assertSuccessful();
            $this->assertSame($before, hash_file('sha256', $file));
            $this->assertSame('legacy-user', User::first()->id);
            $this->assertNull((new RecordStore)->get('sessions', 'old-token'));
            $issue = (new RecordStore)->forWorkspace('legacy-workspace')->require('issues', 'old-a');
            $this->assertSame(['old-b'], $issue['relatedIssueIds']);
            $this->assertSame(4, $issue['revision']);
            $this->assertSame('原作者', $issue['history'][0]['actor']);
            $this->assertArrayNotHasKey('blockedBy', $issue);
            $converted = (new RecordStore)->forWorkspace('legacy-workspace')->require('issues', 'old-b');
            $this->assertSame('task', $converted['type']);
            $this->assertSame(5, $converted['revision']);
            $this->assertSame('feature', $converted['history'][2]['changes'][0]['before']);
            $this->postJson('/api/auth/login', ['email' => $account['email'], 'password' => 'test-local-password'])->assertOk()->assertJsonPath('data.workspace.id', 'legacy-workspace');
            $this->assertNull(User::first()->legacy_salt);
            $this->postJson('/api/rpc/getWorkspace', ['requestId' => 'import-read'])->assertOk()->assertJsonPath('data.projects.0.revision', 4);
            $this->artisan('tasktracker:import-legacy', ['file' => $file])->assertFailed();
            $this->assertSame($before, hash_file('sha256', $file));
        } finally { unlink($file); }
    }
}
