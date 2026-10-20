<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\RecordStore;
use App\Services\DemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_is_repeatable_scoped_and_creates_real_relations_and_time(): void
    {
        $store = new RecordStore;
        $store->put('workspaces', 'personal', ['id' => 'personal', 'name' => '保留']);
        $store->forWorkspace('personal')->put('projects', 'personal-project', ['id' => 'personal-project', 'name' => '不可刪除']);
        $seed = app(DemoData::class);
        $seed->seed(password: 'seed-test-password');
        $tenant = $store->forWorkspace(DemoData::WORKSPACE_ID);
        $this->assertSame(['first', 'second'], array_column($tenant->all('projects'), 'name'));
        $this->assertCount(0, $tenant->all('savedViews'));
        $this->assertCount(14, $tenant->all('issues'));
        $this->assertCount(6, $tenant->all('timeEntries'));
        $this->assertSame(4, count(array_filter($tenant->all('issues'), fn ($issue) => $issue['parentIssueId'] !== '')));
        $this->assertSame(4, count(array_filter($tenant->all('issues'), fn ($issue) => $issue['relatedIssueIds'] !== [])));
        $this->assertTrue(Hash::check('seed-test-password', User::find(DemoData::USER_ID)->password));
        $before = DB::table('records')->count();
        $this->assertFalse($seed->seed()['created']);
        $this->assertSame($before, DB::table('records')->count());
        $project = $tenant->all('projects')[0];
        $tenant->put('projects', $project['id'], [...$project, 'name' => '自行修改']);
        $seed->seed();
        $this->assertSame('自行修改', $tenant->require('projects', $project['id'])['name']);
        $seed->seed(reset: true, password: 'seed-test-password');
        $this->assertSame(['first', 'second'], array_column($tenant->all('projects'), 'name'));
        $this->assertCount(14, $tenant->all('issues'));
        $this->assertSame('不可刪除', $store->forWorkspace('personal')->require('projects', 'personal-project')['name']);
        $this->postJson('/api/auth/login', ['email' => DemoData::EMAIL, 'password' => 'seed-test-password'])->assertOk()->assertJsonPath('data.workspace.name', 'f-ws');
    }

    public function test_seed_refuses_production_and_test_database_is_always_memory(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        app()->instance('env', 'production');
        $this->artisan('tasktracker:seed-demo')->assertFailed();
        $this->assertSame(0, User::count());
    }
}
