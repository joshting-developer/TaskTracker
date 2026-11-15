<?php

namespace Tests\Concerns;

use App\Models\HttpOperationLog;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** 管理登入、期間聚合及讀取範圍的跨引擎隔離驗證 */
trait AdminDashboardCases
{
    /**
     * 注入只有本測試知道的管理帳密, 不使用現用秘密
     *
     * @return void
     */
    private function configureAdmin(): void
    {
        config(['admin.username' => 'isolated_admin', 'admin.password_hash' => Hash::make('isolated-admin-password')]);
    }

    /** 未配置及一般帳號都沒有平台管理資格
     *
     * @return void 未配置及一般帳號都沒有平台管理資格
     */
    public function test_admin_requires_independent_credentials_and_configuration(): void
    {
        config(['admin.username' => '', 'admin.password_hash' => '']);
        $this->postJson('/api/admin/session', ['username' => 'isolated_admin', 'password' => 'isolated-admin-password'])->assertUnauthorized();
        $this->configureAdmin();
        $this->getJson('/api/admin/statistics')->assertUnauthorized();
        $this->getJson('/api/admin/workspaces')->assertUnauthorized();
        $user = User::factory()->create(['id' => (string) Str::uuid()]);
        $this->actingAs($user)->withHeaders(['Authorization' => 'Bearer arbitrary-owner-token'])->getJson('/api/admin/session')->assertUnauthorized();
        $this->postJson('/api/admin/session', ['username' => 'isolated_admin', 'password' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/admin/session', ['username' => 'someone_else', 'password' => 'isolated-admin-password'])->assertUnauthorized();
        $response = $this->postJson('/api/admin/session', ['username' => 'isolated_admin', 'password' => 'isolated-admin-password'])->assertOk()->assertHeader('X-CSRF-TOKEN');
        $this->assertSame(['ok' => true, 'data' => ['username' => 'isolated_admin']], $response->json());
        $this->getJson('/api/admin/session')->assertOk();
        $this->deleteJson('/api/admin/session')->assertOk();
        $this->getJson('/api/admin/statistics')->assertUnauthorized();
        $this->assertAuthenticatedAs($user);
    }

    /** Session固定30分鐘到期且配置輪替立即失效
     *
     * @return void Session固定30分鐘到期且配置輪替立即失效
     */
    public function test_admin_session_expires_and_password_rotation_revokes_it(): void
    {
        $this->configureAdmin();
        $this->postJson('/api/admin/session', ['username' => 'isolated_admin', 'password' => 'isolated-admin-password'])->assertOk();
        $this->travel(30)->minutes();
        $this->getJson('/api/admin/statistics')->assertUnauthorized();
        $this->travelBack();
        $this->postJson('/api/admin/session', ['username' => 'isolated_admin', 'password' => 'isolated-admin-password'])->assertOk();
        config(['admin.password_hash' => Hash::make('a-new-isolated-password')]);
        $this->getJson('/api/admin/statistics')->assertUnauthorized();
        $this->getJson('/api/admin/workspaces')->assertUnauthorized();
    }

    /** 精確UTC期間、排除失敗與驗證操作、補零並只讀聚合
     *
     * @return void 精確UTC期間、排除失敗與驗證操作、補零並只讀聚合
     */
    public function test_admin_statistics_are_bounded_aggregates_without_business_content(): void
    {
        $this->configureAdmin();
        $this->travelTo(CarbonImmutable::parse('2030-11-15 12:30:00', 'UTC'));
        $priorUsers = User::count();
        $priorWorkspaces = Workspace::count();
        $workspace = Workspace::create(['public_id' => (string) Str::uuid(), 'name' => 'Isolated workspace']);
        $user = User::factory()->create(['id' => (string) Str::uuid()]);
        $other = User::factory()->create(['id' => (string) Str::uuid()]);
        $this->postJson('/api/admin/session', ['username' => 'isolated_admin', 'password' => 'isolated-admin-password'])->assertOk();
        $base = ['workspace_public_id' => $workspace->public_id, 'user_id' => $user->id, 'route_name' => 'rpc.createIssue', 'method' => 'POST', 'status' => 200, 'duration_ms' => 5, 'occurred_at' => '2030-11-09 00:00:00'];
        HttpOperationLog::insert([
            $base,
            [...$base, 'route_name' => 'public.issues.comment', 'occurred_at' => '2030-11-15 23:59:59.999999'],
            [...$base, 'route_name' => 'rpc.logTime', 'occurred_at' => '2030-11-15 10:00:00'],
            [...$base, 'status' => 403],
            [...$base, 'route_name' => 'auth.login'],
            [...$base, 'route_name' => 'admin.login'],
            [...$base, 'method' => 'GET'],
            [...$base, 'user_id' => null],
            [...$base, 'workspace_public_id' => null],
            [...$base, 'user_id' => $other->id, 'occurred_at' => '2030-11-08 23:59:59.999999'],
            [...$base, 'user_id' => $other->id, 'occurred_at' => '2030-11-16 00:00:00'],
        ]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->getJson('/api/admin/statistics?days=7')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $queries = collect(DB::getQueryLog())->pluck('query')->all();
        DB::disableQueryLog();
        $this->assertSame(['users' => $priorUsers + 2, 'workspaces' => $priorWorkspaces + 1], $response->json('data.totals'));
        $this->assertSame(['users' => 1, 'workspaces' => 1, 'requests' => 3], $response->json('data.active'));
        $this->assertCount(7, $response->json('data.daily'));
        $this->assertSame(['date' => '2030-11-09', 'requests' => 1, 'issues' => 1, 'comments' => 0, 'timeEntries' => 0], $response->json('data.daily.0'));
        $this->assertSame(0, $response->json('data.daily.1.requests'));
        $this->assertSame(2, $response->json('data.daily.6.requests'));
        $this->assertSame('2030-11-15', $response->json('data.period.through'));
        $sql = strtolower(implode("\n", $queries));
        $this->assertStringNotContainsString('issue_comments', $sql);
        $this->assertStringNotContainsString('audit_events', $sql);
        $this->assertStringNotContainsString('todos', $sql);
        $this->assertStringContainsString('occurred_at', $sql);
        $this->assertStringContainsString('limit 30', $sql);
        foreach (['password', 'email', 'token_id', 'request_id', 'description', 'content'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $response->getContent());
        }
        $this->getJson('/api/admin/statistics?days=30')->assertOk()->assertJsonCount(30, 'data.daily');
        $this->getJson('/api/admin/statistics?days=365')->assertUnprocessable();
        $this->getJson('/api/admin/workspaces?before=-1')->assertUnprocessable();
    }

    /** 工作區游標固定20筆且不載入成員與資料內容
     *
     * @return void 工作區游標固定20筆且不載入成員與資料內容
     */
    public function test_admin_workspaces_use_stable_keyset_and_minimal_projection(): void
    {
        $this->configureAdmin();
        for ($i = 0; $i < 25; $i++) {
            Workspace::create(['public_id' => (string) Str::uuid(), 'name' => 'Workspace '.$i, 'legacy_attributes' => ['private' => 'never-return-this']]);
        }
        $this->postJson('/api/admin/session', ['username' => 'isolated_admin', 'password' => 'isolated-admin-password'])->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $this->getJson('/api/admin/workspaces')->assertOk();
        $sql = strtolower(implode("\n", collect(DB::getQueryLog())->pluck('query')->all()));
        DB::disableQueryLog();
        $this->assertCount(20, $first->json('data.items'));
        $this->assertSame('Workspace 24', $first->json('data.items.0.name'));
        $this->assertSame(['id', 'name', 'createdAt'], array_keys($first->json('data.items.0')));
        $this->assertStringContainsString('limit 21', $sql);
        $this->assertStringNotContainsString('legacy_attributes', $sql);
        $this->assertStringNotContainsString('workspace_members', $sql);
        $second = $this->getJson('/api/admin/workspaces?before='.$first->json('data.nextBefore'))->assertOk();
        $this->assertGreaterThanOrEqual(5, count($second->json('data.items')));
        $this->assertSame('Workspace 4', $second->json('data.items.0.name'));
        $this->assertEmpty(array_intersect(array_column($first->json('data.items'), 'id'), array_column($second->json('data.items'), 'id')));
    }

    /** 錯誤密碼不會取得Session, 且同來源第六次被限流
     *
     * @return void 錯誤密碼不會取得Session, 且同來源第六次被限流
     */
    public function test_admin_login_is_rate_limited(): void
    {
        $this->configureAdmin();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/admin/session', ['username' => 'isolated_admin', 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/api/admin/session', ['username' => 'isolated_admin', 'password' => 'isolated-admin-password'])->assertTooManyRequests();
    }
}
