<?php

namespace Tests\Feature;

use App\Factories\RecordStoreFactory;
use App\Factories\TodosFactory;
use App\Factories\WorkspaceOperationServices;
use App\Factories\WorkspaceServicesFactory;
use App\Http\ApiExecution;
use App\Repositories\RecordStore;
use App\Repositories\TimeReportRepository;
use App\Services\RequestReplay;
use App\Services\WorkspaceService;
use Closure;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** TT-233：核心依賴鏈由Container解析、按需初始化、可替換協作者且不跨操作／租戶保留狀態。 */
class WorkspaceServiceContainerTest extends TestCase
{
    use RefreshDatabase;

    /** 測試期間建立的服務組與替換協作者的呼叫次數。 */
    private \ArrayObject $bundles;

    /**
     * 以可觀察的工廠取代正式工廠，記錄每次操作的服務組。
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $bundles = $this->bundles = new \ArrayObject;
        $this->app->bind(WorkspaceServicesFactory::class, fn ($app) => new class($app->make(RecordStoreFactory::class), $app->make(TimeReportRepository::class), $bundles) extends WorkspaceServicesFactory
        {
            /**
             * 保留紀錄陣列參照。
             *
             * @param  RecordStoreFactory  $stores  Store工廠
             * @param  TimeReportRepository  $reports  報表查詢
             * @param  \ArrayObject  $bundles  紀錄
             */
            public function __construct(RecordStoreFactory $stores, TimeReportRepository $reports, private \ArrayObject $bundles)
            {
                parent::__construct($stores, $reports);
            }

            /**
             * 建立並記錄服務組。
             *
             * @param  array<string,mixed>  $workspaceSession  可信Session
             * @return WorkspaceOperationServices 服務組
             */
            public function forSession(array $workspaceSession): WorkspaceOperationServices
            {
                return $this->bundles[] = parent::forSession($workspaceSession);
            }
        });
    }

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
     * 註冊新帳號與工作區並回傳身分。
     *
     * @param  string  $name  名稱
     * @return array<string,mixed> 登入身分
     */
    private function register(string $name): array
    {
        return $this->postJson('/api/auth/register', ['name' => $name, 'email' => strtolower($name).'-'.Str::uuid().'@example.test', 'password' => 'test-local-password', 'workspaceName' => $name])->assertOk()->json('data');
    }

    /**
     * 最近一次操作的服務組。
     *
     * @return WorkspaceOperationServices 服務組
     */
    private function last(): WorkspaceOperationServices
    {
        return $this->bundles[count($this->bundles) - 1];
    }

    /**
     * 讀出Store限定的工作區ID。
     *
     * @param  RecordStore  $store  資料入口
     * @return string|null 工作區公開ID
     */
    private function storeWorkspace(RecordStore $store): ?string
    {
        return Closure::bind(fn () => $this->workspaceId, $store, RecordStore::class)();
    }

    /**
     * 真正入口皆可由Container解析，且無建構子循環；WorkspaceService不是singleton。
     *
     * @return void
     */
    public function test_core_entry_points_resolve_without_cycles(): void
    {
        foreach ([ApiExecution::class, WorkspaceService::class, TodosFactory::class, DemoSeeder::class] as $class) {
            $this->assertInstanceOf($class, $this->app->make($class));
        }
        $this->assertNotSame($this->app->make(WorkspaceService::class), $this->app->make(WorkspaceService::class), '不註冊為共享實例');
    }

    /**
     * 單一操作只初始化需要的領域服務。
     *
     * @return void
     */
    public function test_operations_initialize_only_needed_services(): void
    {
        $this->register('Lazy');
        $project = $this->rpc('createProject', ['name' => 'Lazy', 'code' => 'LZY'])->assertOk()->json('data');
        $this->assertSame(['access', 'operationAccess', 'projects', 'signals'], $this->last()->created());
        $issue = $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Card'])->assertOk()->json('data');
        $this->assertSame(['access', 'operationAccess', 'issues', 'signals'], $this->last()->created(), '未帶看板不建立看板用例');
        $this->rpc('updateIssue', ['id' => $issue['id'], 'title' => 'Card 2', 'expectedRevision' => $issue['revision']])->assertOk();
        $this->assertSame(['access', 'operationAccess', 'issues', 'signals'], $this->last()->created());
        $board = $this->rpc('createBoard', ['projectId' => $project['id'], 'name' => 'B', 'selectedIssueIds' => []])->assertOk()->json('data');
        $this->rpc('createIssue', ['projectId' => $project['id'], 'title' => 'On board', 'boardId' => $board['id']])->assertOk();
        $this->assertSame(['access', 'operationAccess', 'issues', 'boards', 'signals'], $this->last()->created(), '帶看板才建立看板用例');
        $this->rpc('logTime', ['issueId' => $issue['id'], 'workDate' => '2026-10-05', 'minutes' => 30, 'note' => 'n'])->assertOk();
        $this->assertSame(['access', 'operationAccess', 'time', 'signals'], $this->last()->created());
    }

    /**
     * 協作者以Container替換即可生效，不需修改被測類別。
     *
     * @return void
     */
    public function test_replay_collaborator_is_replaceable_through_container(): void
    {
        $calls = new \ArrayObject;
        $this->app->bind(RequestReplay::class, fn () => new class($calls) extends RequestReplay
        {
            /**
             * 保留計數參照。
             *
             * @param  \ArrayObject  $calls  呼叫紀錄
             */
            public function __construct(private \ArrayObject $calls)
            {
                parent::__construct();
            }

            /**
             * 記錄後委派原本的重播流程。
             *
             * @param  RecordStore  $store  資料入口
             * @param  string  $kind  receipt種類
             * @param  string  $userId  操作者
             * @param  string  $operation  操作
             * @param  array<string,mixed>  $input  輸入
             * @param  Closure  $write  寫入
             * @return array<string,mixed> 結果
             */
            public function execute(RecordStore $store, string $kind, string $userId, string $operation, array $input, Closure $write): array
            {
                $this->calls[] = $operation;

                return parent::execute($store, $kind, $userId, $operation, $input, $write);
            }
        });
        $this->register('Replace');
        $this->rpc('createProject', ['name' => 'Replace', 'code' => 'RPL'])->assertOk();
        $this->assertSame(['createProject'], $calls->getArrayCopy());
    }

    /**
     * 不同使用者／工作區連續操作，各自取得新服務組，Store與身分不互相沿用。
     *
     * @return void
     */
    public function test_each_operation_gets_fresh_scoped_services(): void
    {
        $first = $this->register('First');
        $this->rpc('createProject', ['name' => 'First', 'code' => 'FST'])->assertOk();
        $firstBundle = $this->last();
        Auth::logout();
        $second = $this->register('Second');
        $this->rpc('createProject', ['name' => 'Second', 'code' => 'SCD'])->assertOk();
        $secondBundle = $this->last();
        $this->assertNotSame($firstBundle, $secondBundle);
        $this->assertSame($first['workspace']['id'], $this->storeWorkspace($firstBundle->store));
        $this->assertSame($second['workspace']['id'], $this->storeWorkspace($secondBundle->store));
        $this->assertSame($second['user']['id'], $secondBundle->workspaceSession['user']['id']);
        $this->assertNotSame($firstBundle->access(), $secondBundle->access(), '授權協作者不跨操作共用');
    }
}
