<?php

namespace Tests\Feature;

use App\Console\Commands\ImportLegacy;
use App\Events\ProjectChanged;
use App\Factories\SmtpTransportFactory;
use App\Factories\TodosFactory;
use App\Repositories\ProjectReadRepository;
use App\Services\AccountService;
use App\Services\ProjectSignals;
use App\Services\SmtpService;
use App\Services\WorkspaceMemberships;
use Closure;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;

/** TT-234：其餘協作者由Container解析、外部SMTP可替換，連續不同租戶的訊號與待辦用例不殘留範圍。 */
class PeripheryContainerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 帳號、工作區資格、SMTP、待辦、CLI與Seeder入口皆可由Container解析。
     *
     * @return void
     */
    public function test_periphery_entry_points_resolve(): void
    {
        foreach ([AccountService::class, WorkspaceMemberships::class, SmtpService::class, TodosFactory::class, ImportLegacy::class, DemoSeeder::class] as $class) {
            $this->assertInstanceOf($class, $this->app->make($class));
        }
    }

    /**
     * SMTP transport由Factory建立，可替換成不連線的實作；verify仍不寄信、失敗回SMTP_ERROR。
     *
     * @return void
     */
    public function test_smtp_transport_is_replaceable_without_network(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['host' => 'smtp.invalid', 'port' => 587, 'scheme' => null, 'username' => 'u', 'password' => 'p'], 'mail.from.address' => 'a@example.test']);
        $made = new \ArrayObject;
        $this->app->bind(SmtpTransportFactory::class, fn () => new class($made) extends SmtpTransportFactory
        {
            /**
             * 保留紀錄物件。
             *
             * @param  \ArrayObject  $made  建立紀錄
             */
            public function __construct(private \ArrayObject $made) {}

            /**
             * 回傳start不連線的transport。
             *
             * @param  string  $host  主機
             * @param  int  $port  埠
             * @param  bool|null  $secure  TLS
             * @return EsmtpTransport 不連線的transport
             */
            public function make(string $host, int $port, ?bool $secure): EsmtpTransport
            {
                $this->made[] = "{$host}:{$port}";

                return new class($host, $port, $secure) extends EsmtpTransport
                {
                    /**
                     * 不建立網路連線。
                     *
                     * @return void
                     */
                    public function start(): void {}

                    /**
                     * 不需關閉連線。
                     *
                     * @return void
                     */
                    public function stop(): void {}
                };
            }
        });
        $this->assertTrue($this->app->make(SmtpService::class)->verify()['verified']);
        $this->assertSame(['smtp.invalid:587'], $made->getArrayCopy());
    }

    /**
     * 連續為不同工作區送出Project訊號，各自只送到自己的私有頻道。
     *
     * @return void
     */
    public function test_consecutive_tenants_do_not_share_signal_scope(): void
    {
        config(['broadcasting.default' => 'reverb']);
        Event::fake([ProjectChanged::class]);
        foreach ([['ws-a', 'project-a'], ['ws-b', 'project-b'], ['ws-a', 'project-c']] as [$workspace, $project]) {
            DB::transaction(fn () => (new ProjectSignals(['workspace' => ['id' => $workspace], 'user' => ['id' => 'u']], app(ProjectReadRepository::class)))->changed($project));
        }
        $channels = [];
        Event::assertDispatched(ProjectChanged::class, function (ProjectChanged $event) use (&$channels) {
            $channels[] = $event->broadcastOn()[0]->name;

            return true;
        });
        $this->assertSame(['private-workspaces.ws-a.projects.project-a', 'private-workspaces.ws-b.projects.project-b', 'private-workspaces.ws-a.projects.project-c'], $channels);
    }

    /**
     * 同一行程連續為兩個工作區建立待辦用例，資料入口與授權各自限定。
     *
     * @return void
     */
    public function test_todos_factory_builds_fresh_scope_per_session(): void
    {
        $factory = $this->app->make(TodosFactory::class);
        $first = $factory->forSession(['user' => ['id' => 'u1'], 'workspace' => ['id' => 'ws-1'], 'permissions' => []]);
        $second = $factory->forSession(['user' => ['id' => 'u2'], 'workspace' => ['id' => 'ws-2'], 'permissions' => []]);
        $scope = fn (object $todos) => Closure::bind(fn () => [Closure::bind(fn () => $this->workspaceId, $this->store, $this->store::class)(), $this->userId, spl_object_id($this->access)], $todos, $todos::class)();
        [$ws1, $user1, $access1] = $scope($first);
        [$ws2, $user2, $access2] = $scope($second);
        $this->assertSame(['ws-1', 'u1'], [$ws1, $user1]);
        $this->assertSame(['ws-2', 'u2'], [$ws2, $user2]);
        $this->assertNotSame($access1, $access2);
    }
}
