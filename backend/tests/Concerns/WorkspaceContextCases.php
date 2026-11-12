<?php

namespace Tests\Concerns;

use App\Authorization\ProjectAccess;
use App\Authorization\ProjectChannelAccess;
use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Factories\ProjectAccessFactory;
use App\Factories\RecordStoreFactory;
use App\Factories\WorkspaceContextFactory;
use App\Http\RealtimeSession;
use App\Http\WorkspaceSession;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Repositories\RecordStore;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** memory與原生MySQL共用Context／Factory的真資料資格與生命周期案例。 */
trait WorkspaceContextCases
{
    /**
     * Context工廠可由Container解析、不同Session互不共用，且每次都重驗工作區資格。
     *
     * @return void
     */
    public function test_context_factories_are_container_resolvable_isolated_and_revalidate_membership(): void
    {
        $fixtures = [];
        foreach (['A', 'B'] as $label) {
            Auth::logout();
            $identity = $this->postJson('/api/auth/register', ['name' => $label, 'email' => Str::uuid().'@example.test', 'password' => 'context-test-password', 'workspaceName' => $label])->assertOk()->json('data');
            $project = $this->postJson('/api/rpc/createProject', ['name' => $label, 'code' => 'CTX'.$label, 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
            $fixtures[] = ['identity' => $identity, 'project' => $project, 'user' => User::findOrFail($identity['user']['id'])];
        }
        [$a, $b] = $fixtures;
        $factory = app(WorkspaceContextFactory::class);
        $accesses = app(ProjectAccessFactory::class);
        $stores = app(RecordStoreFactory::class);
        foreach ([WorkspaceSession::class, RealtimeSession::class, ProjectChannelAccess::class] as $class) {
            $this->assertInstanceOf($class, app($class));
        }
        // 同一批長期重用的協作者依序A→B→A，不共用帶租戶的Store或授權快照。
        foreach ([$a, $b, $a] as $fixture) {
            $context = $factory->verify($fixture['user'], $fixture['identity']['workspace']['id']);
            $this->assertInstanceOf(WorkspaceContext::class, $context);
            $this->assertSame($fixture['user']->id, $context->userId);
            $accesses->forContext($context)->require($fixture['project']['id'], 'project.read');
            $store = $stores->forContext($context);
            $foreign = $fixture === $a ? $b : $a;
            $this->assertNull($store->get('projects', $foreign['project']['id']));
            try {
                $accesses->forContext($context)->require($foreign['project']['id'], 'project.read');
                $this->fail('不可跨租戶讀取');
            } catch (DomainError $error) {
                $this->assertContains($error->errorCode, ['NOT_FOUND', 'FORBIDDEN']);
            }
            $copy = $context->legacyIdentity();
            $copy['workspace']['id'] = 'forged';
            $copy['permissions'][] = 'forged';
            $this->assertSame($fixture['identity']['workspace']['id'], $context->legacyIdentity()['workspace']['id']);
            $this->assertNotContains('forged', $context->legacyIdentity()['permissions']);
        }
        // HTTP payload的角色／身分／Workspace不能建立可信Context。
        $request = Request::create('/api/realtime/authorize', 'POST', ['workspaceId' => $b['identity']['workspace']['id'], 'user' => $b['identity']['user'], 'permissions' => ['workspace.ownership']]);
        $request->setUserResolver(fn () => $a['user']);
        $session = new Store('context-test', new ArraySessionHandler(120));
        $session->start();
        $session->put('workspace_id', $a['identity']['workspace']['id']);
        $request->setLaravelSession($session);
        $context = app(WorkspaceSession::class)->verified($request);
        $this->assertSame($a['user']->id, $context->userId);
        $this->assertSame($a['identity']['workspace']['id'], $context->workspaceId);
        $this->assertSame($context->legacyIdentity(), app(WorkspaceSession::class)->context($request));
        // 相同Factory再取資格必須讀DB，失效資格不能由之前的Context恢復。
        WorkspaceMember::where('user_id', $a['user']->id)->update(['disabled_at' => now()]);
        try {
            $factory->verify($a['user'], $a['identity']['workspace']['id']);
            $this->fail('必須重新驗證成員資格');
        } catch (DomainError $error) {
            $this->assertSame('UNAUTHENTICATED', $error->errorCode);
        }
    }

    /**
     * 即時頻道授權路徑不重複建立Store，讀取仍限定在目前Workspace與Project。
     *
     * @return void
     */
    public function test_context_channel_path_reduces_duplicate_store_setup_and_keeps_scoped_reads(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'context', 'email' => Str::uuid().'@example.test', 'password' => 'context-test-password', 'workspaceName' => 'context'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'context', 'code' => 'CTX', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $user = User::findOrFail($identity['user']['id']);
        $context = app(WorkspaceContextFactory::class)->verify($user, $identity['workspace']['id']);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            // 舊版頻道路徑的控制組：先建無scope Store，再forWorkspace，重複初始化。
            $legacy = new ProjectAccess((new RecordStore)->forWorkspace($context->workspaceId), $context->legacyIdentity());
            $legacy->require($project['id'], 'project.read');
            $legacy->require($project['id'], 'issue.read');
            $before = count(DB::getQueryLog());
            DB::flushQueryLog();
            app(ProjectChannelAccess::class)->authorize($context, 'private-workspaces.'.$context->workspaceId.'.projects.'.$project['id'], '123.456');
            $queries = DB::getQueryLog();
            $this->assertLessThanOrEqual($before, count($queries));
            $this->assertFalse(collect($queries)->contains(fn ($q) => preg_match('/from ["`]?time_entries|from ["`]?issues|from ["`]?issue_history/i', $q['query']) === 1));
        } finally {
            DB::disableQueryLog();
        }
    }

    /** Container可替換真正授權協作者，不修改產品碼或把actor註冊成singleton。 @return void */
    public function test_channel_access_factory_is_replaceable_in_container(): void
    {
        $identity = $this->postJson('/api/auth/register', ['name' => 'replace', 'email' => Str::uuid().'@example.test', 'password' => 'context-test-password', 'workspaceName' => 'replace'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'replace', 'code' => 'REPLACE', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $context = app(WorkspaceContextFactory::class)->verify(User::findOrFail($identity['user']['id']), $identity['workspace']['id']);
        $access = app(ProjectAccessFactory::class)->forContext($context);
        $factory = \Mockery::mock(ProjectAccessFactory::class);
        $factory->shouldReceive('forContext')->once()->with($context)->andReturn($access);
        $this->app->instance(ProjectAccessFactory::class, $factory);
        $channel = 'private-workspaces.'.$context->workspaceId.'.projects.'.$project['id'];
        $this->app->make(ProjectChannelAccess::class)->authorize($context, $channel, '123.456');
        $this->assertTrue((new \ReflectionClass($context))->isReadOnly());
    }
}
