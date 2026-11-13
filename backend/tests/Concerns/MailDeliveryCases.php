<?php

namespace Tests\Concerns;

use App\Data\MailDeliveryData;
use App\Jobs\SendTransactionalMail;
use App\Mail\TransactionalMessage;
use App\Models\MailDelivery;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\MailConnectionService;
use App\Services\MailDeliveryService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;

/** 信件測試只用合成帳號、Mail fake與可替換連線, 不連真SMTP */
trait MailDeliveryCases
{
    /** 建立獨立收件帳號與假的SMTP預連線
     * @return array{array,MailConnectionService} 合成User與連線
     */
    private function mailFixture(): array
    {
        Mail::fake();
        Queue::fake();
        config(['mail.default' => 'array', 'delivery.connection' => 'redis']);
        $identity = $this->postJson('/api/auth/register', ['name' => 'Mail recipient', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-mail-password', 'workspaceName' => 'Mail'])->assertOk()->json('data');
        MailDelivery::where('status', 'pending')->update(['next_attempt_at' => now()->addDay()]); // Isolate old synthetic browser outbox rows within the test transaction.
        Queue::fake(); // Count only delivery jobs from this fixture onward, after signup verification.
        $connection = new class(Mail::getFacadeRoot()) extends MailConnectionService
        {
            public bool $fail = false;

            public ?\Closure $onConnect = null;

            public int $calls = 0;

            /** 不連外, 可注入連線失敗或寄前資格變更
             * @return void
             */
            public function connect(): void
            {
                $this->calls++;
                if ($this->onConnect) {
                    ($this->onConnect)();
                }
                if ($this->fail) {
                    throw new RuntimeException('private SMTP error must not escape');
                }
            }
        };
        $this->app->instance(MailConnectionService::class, $connection);

        return [$identity['user'], $connection];
    }

    /** 保存唯一事件且內容加密, Queue只帶ID；重複Job不再寄
     * @return void
     */
    public function test_mail_outbox_is_encrypted_queued_after_commit_and_idempotent(): void
    {
        [$user] = $this->mailFixture();
        $service = $this->app->make(MailDeliveryService::class);
        $data = new MailDeliveryData($user['id'], (string) Str::uuid(), 'diagnostic', 'Mail test', 'SYNTHETIC-CONTENT-ONLY', now()->addHour());
        $row = $service->enqueue($data);
        Queue::assertPushed(SendTransactionalMail::class, fn ($job) => $job->id === $row->id && $job->queue === 'mail');
        $this->assertSame($row->id, $service->enqueue($data)->id);
        Queue::assertPushed(SendTransactionalMail::class, 1);
        $this->assertStringNotContainsString('SYNTHETIC-CONTENT-ONLY', DB::table('mail_deliveries')->where('id', $row->id)->value('payload'));
        $this->assertStringNotContainsString($user['email'], DB::table('mail_deliveries')->where('id', $row->id)->value('recipient'));
        $this->assertStringNotContainsString('SYNTHETIC', serialize(new SendTransactionalMail($row->id)));
        $service->deliver($row->id);
        $service->deliver($row->id);
        Mail::assertSent(TransactionalMessage::class, 1);
        Mail::assertSent(TransactionalMessage::class, fn ($mail) => $mail->hasTo($user['email']) && str_contains($mail->render(), 'SYNTHETIC-CONTENT-ONLY'));
        $this->assertSame('sent', $row->fresh()->status);
        $this->assertSame(1, $row->fresh()->attempts);
        $this->assertNull($row->fresh()->payload);
        $this->assertNotNull($row->fresh()->sent_at);
    }

    /** 業務回滾不派Job也不留outbox
     * @return void
     */
    public function test_rolled_back_mail_is_not_dispatched(): void
    {
        [$user] = $this->mailFixture();
        $service = $this->app->make(MailDeliveryService::class);
        try {
            DB::transaction(function () use ($service, $user) {
                $service->enqueue(new MailDeliveryData($user['id'], 'rollback', 'diagnostic', 'Mail test', 'rollback', now()->addHour()));
                throw new RuntimeException('rollback fixture');
            });
        } catch (RuntimeException) {
        }
        Queue::assertNothingPushed();
        $this->assertSame(0, MailDelivery::where('user_id', $user['id'])->where('kind', 'diagnostic')->count());
        Mail::assertNothingSent();
    }

    /** 連線失敗有限重試且不外洩原SMTP錯誤
     * @return void
     */
    public function test_connection_failures_retry_three_times_then_stop(): void
    {
        [$user, $connection] = $this->mailFixture();
        $connection->fail = true;
        $service = $this->app->make(MailDeliveryService::class);
        $row = $service->enqueue(new MailDeliveryData($user['id'], 'retry', 'diagnostic', 'Mail test', 'retry', now()->addHour()));
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $service->deliver($row->id);
                $this->fail('Expected connection error');
            } catch (RuntimeException $error) {
                $this->assertSame('SMTP_CONNECTION_FAILED', $error->getMessage());
            }
            $this->assertSame($attempt, $row->fresh()->attempts);
            $this->travel(121)->seconds();
        }
        $this->assertSame('failed', $row->fresh()->status);
        $this->assertSame('SMTP_CONNECTION_FAILED', $row->fresh()->error_code);
        $this->assertNull($row->fresh()->payload);
        $service->deliver($row->id);
        $this->assertSame(3, $connection->calls);
        Mail::assertNothingSent();
        $this->travelBack();
    }

    /** SMTP交付中斷結果不明時不自動再寄
     * @return void
     */
    public function test_uncertain_send_and_worker_interruption_do_not_resend(): void
    {
        [$user] = $this->mailFixture();
        $factory = Mockery::mock(Factory::class);
        $mailer = Mockery::mock();
        $factory->shouldReceive('mailer')->once()->andReturn($mailer);
        $mailer->shouldReceive('to')->once()->andReturnSelf();
        $mailer->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP secret body must not escape'));
        $this->app->instance(Factory::class, $factory);
        $service = $this->app->make(MailDeliveryService::class);
        $row = $service->enqueue(new MailDeliveryData($user['id'], 'ambiguous', 'diagnostic', 'Mail test', 'private', now()->addHour()));
        $service->deliver($row->id);
        $service->deliver($row->id);
        $this->assertSame('uncertain', $row->fresh()->status);
        $this->assertSame('SMTP_DELIVERY_UNCERTAIN', $row->fresh()->error_code);
        $this->assertNull($row->fresh()->payload);
        $stale = $service->enqueue(new MailDeliveryData($user['id'], 'worker-killed', 'diagnostic', 'Mail test', 'private', now()->addHour()));
        $stale->update(['status' => 'sending', 'started_at' => now()->subMinutes(2)]);
        $service->deliver($stale->id);
        $this->assertSame('uncertain', $stale->fresh()->status);
        $this->assertSame('WORKER_INTERRUPTED', $stale->fresh()->error_code);
    }

    /** 寄前重驗地址、資格與驗證狀態, 變更後不送舊信
     * @return void
     */
    public function test_recipient_changes_expiry_and_verification_cancel_delivery(): void
    {
        [$user, $connection] = $this->mailFixture();
        $service = $this->app->make(MailDeliveryService::class);
        $verified = $service->enqueue(new MailDeliveryData($user['id'], 'verification', 'verification', 'Verify', 'CODE', now()->addHour()));
        $connection->onConnect = fn () => User::whereKey($user['id'])->update(['email_verified_at' => now()]);
        $service->deliver($verified->id);
        $this->assertSame('skipped', $verified->fresh()->status);
        $connection->onConnect = null;
        $expired = $service->enqueue(new MailDeliveryData($user['id'], 'expired', 'diagnostic', 'Test', 'expired', now()->subMinute()));
        $service->deliver($expired->id);
        $this->assertSame('skipped', $expired->fresh()->status);
        $changed = $service->enqueue(new MailDeliveryData($user['id'], 'changed', 'diagnostic', 'Test', 'changed', now()->addHour()));
        User::whereKey($user['id'])->update(['email' => Str::uuid().'@example.test']);
        $service->deliver($changed->id);
        $this->assertSame('skipped', $changed->fresh()->status);
        $disabled = $service->enqueue(new MailDeliveryData($user['id'], 'disabled', 'diagnostic', 'Test', 'disabled', now()->addHour()));
        WorkspaceMember::where('user_id', $user['id'])->update(['disabled_at' => now()]);
        $service->deliver($disabled->id);
        $this->assertSame('skipped', $disabled->fresh()->status);
        Mail::assertNothingSent();
    }

    /** Broker中斷不使呼叫方500, 保留pending可重派
     * @return void
     */
    public function test_queue_outage_preserves_pending_without_blocking_on_smtp(): void
    {
        [$user, $connection] = $this->mailFixture();
        $bus = Mockery::mock(Dispatcher::class);
        $bus->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('broker secret must not escape'));
        $this->app->instance(Dispatcher::class, $bus);
        $row = $this->app->make(MailDeliveryService::class)->enqueue(new MailDeliveryData($user['id'], 'broker', 'diagnostic', 'Test', 'body', now()->addHour()));
        $this->assertSame('pending', $row->fresh()->status);
        $this->assertSame('QUEUE_UNAVAILABLE', $row->fresh()->error_code);
        $this->assertSame(0, $connection->calls);
        Mail::assertNothingSent();
    }

    /** 真正DatabaseQueue消費Job, Mail仍fake, 不會寄真信
     * @return void
     */
    public function test_real_queue_worker_consumes_id_only_job_with_fake_mail(): void
    {
        [$user] = $this->mailFixture();
        $this->app->forgetInstance('queue');
        Queue::clearResolvedInstance('queue');
        $queue = 'isolated-mail-'.Str::uuid();
        config(['delivery.connection' => 'database', 'delivery.queue' => $queue]);
        $row = $this->app->make(MailDeliveryService::class)->enqueue(new MailDeliveryData($user['id'], 'worker', 'diagnostic', 'Worker test', 'worker', now()->addHour()));
        $this->assertSame(1, DB::table('jobs')->where('queue', $queue)->count());
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => $queue, '--once' => true, '--sleep' => 0, '--tries' => 3])->assertSuccessful();
        Mail::assertSent(TransactionalMessage::class, 1);
        $this->assertSame('sent', $row->fresh()->status);
        $this->assertSame(0, DB::table('jobs')->where('queue', $queue)->count());
    }

    /** 恢復掃描有SQL上限, failed回呼會留下安全終態
     * @return void
     */
    public function test_recovery_batch_is_bounded_and_failed_callback_updates_state(): void
    {
        [$user] = $this->mailFixture();
        for ($n = 0; $n < 105; $n++) {
            MailDelivery::create(['id' => (string) Str::uuid(), 'user_id' => $user['id'], 'event_key' => hash('sha256', $user['id'].'|batch|'.$n), 'kind' => 'diagnostic', 'recipient' => $user['email'], 'payload' => ['subject' => 'Test', 'body' => 'body'], 'status' => 'pending', 'next_attempt_at' => now(), 'expires_at' => now()->addHour()]);
        }
        $service = $this->app->make(MailDeliveryService::class);
        $this->assertSame(100, $service->dispatchPending());
        Queue::assertPushed(SendTransactionalMail::class, 100);
        $row = MailDelivery::where('user_id', $user['id'])->firstOrFail();
        $row->update(['status' => 'sending', 'started_at' => now()]);
        (new SendTransactionalMail($row->id))->failed(new RuntimeException('sensitive reason'));
        $this->assertSame('uncertain', $row->fresh()->status);
        $this->assertSame('QUEUE_RETRIES_EXHAUSTED', $row->fresh()->error_code);
        $this->assertNull($row->fresh()->payload);
        Mail::assertNothingSent();
    }

    /** Job已被消費卻未寫回結果時, 排程仍會收斂狀態而不補寄
     * @return void
     */
    public function test_scheduler_recovers_stale_sending_without_touching_active_or_sent_mail(): void
    {
        [$user] = $this->mailFixture();
        $service = $this->app->make(MailDeliveryService::class);
        $stale = $service->enqueue(new MailDeliveryData($user['id'], 'stale-scheduler', 'diagnostic', 'Test', 'body', now()->addHour()));
        $active = $service->enqueue(new MailDeliveryData($user['id'], 'active-scheduler', 'diagnostic', 'Test', 'body', now()->addHour()));
        $stale->update(['status' => 'sending', 'started_at' => now()->subMinutes(2)]);
        $active->update(['status' => 'sending', 'started_at' => now()]);
        $this->assertSame(0, $service->dispatchPending());
        $this->assertSame('uncertain', $stale->fresh()->status);
        $this->assertNull($stale->fresh()->payload);
        $this->assertSame('sending', $active->fresh()->status);
        Mail::assertNothingSent();
    }
}
