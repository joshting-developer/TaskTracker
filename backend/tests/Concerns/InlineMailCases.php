<?php

namespace Tests\Concerns;

use App\Data\MailDeliveryData;
use App\Mail\TransactionalMessage;
use App\Models\MailDelivery;
use App\Models\User;
use App\Services\MailDeliveryService;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** 同步寄信仍使用fake, 驗證commit邊界與失敗不重複交付 */
trait InlineMailCases
{
    use MailFixture;

    /** 不派Job, SMTP在最外層業務commit後執行, 同一事件不重寄
     * @return void
     */
    public function test_inline_delivery_is_after_commit_and_deduplicated(): void
    {
        [$user, $connection] = $this->mailFixture();
        config(['delivery.mode' => 'inline']);
        $depth = DB::transactionLevel();
        $connection->onConnect = function () use ($depth) {
            $this->assertSame($depth, DB::transactionLevel());
        };
        $service = $this->app->make(MailDeliveryService::class);
        $data = new MailDeliveryData($user['id'], 'inline-once', 'diagnostic', 'Test', 'synthetic only', now()->addHour());
        $row = DB::transaction(function () use ($service, $data, $connection) {
            $row = $service->enqueue($data);
            $this->assertSame(0, $connection->calls);

            return $row;
        });
        $this->assertSame('sent', $row->fresh()->status);
        $this->assertSame(1, $connection->calls);
        $service->enqueue($data);
        $this->assertSame(1, $connection->calls);
        Queue::assertNothingPushed();
        Mail::assertSent(TransactionalMessage::class, 1);
    }

    /** 連線失敗為明確終態, 不自動重寄或留給無人消費的Queue
     * @return void
     */
    public function test_inline_connection_failure_is_terminal_and_keeps_business_commit(): void
    {
        [$user, $connection] = $this->mailFixture();
        config(['delivery.mode' => 'inline']);
        $connection->fail = true;
        $service = $this->app->make(MailDeliveryService::class);
        $data = new MailDeliveryData($user['id'], 'inline-failure', 'diagnostic', 'Test', 'synthetic', now()->addHour());
        $row = $service->enqueue($data)->fresh();
        $this->assertSame('failed', $row->status);
        $this->assertSame('SMTP_CONNECTION_FAILED', $row->error_code);
        $this->assertNull($row->payload);
        $service->enqueue($data);
        $service->deliver($row->id);
        $this->assertSame(1, $connection->calls);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    /** 回滾不交付, 傳送結果不確定也不能自動再寄
     * @return void
     */
    public function test_inline_rollback_never_sends_and_uncertain_delivery_stops(): void
    {
        [$user, $connection] = $this->mailFixture();
        config(['delivery.mode' => 'inline']);
        $service = $this->app->make(MailDeliveryService::class);
        try {
            DB::transaction(function () use ($service, $user) {
                $service->enqueue(new MailDeliveryData($user['id'], 'inline-rollback', 'diagnostic', 'Test', 'synthetic', now()->addHour()));
                throw new \RuntimeException('synthetic rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, $connection->calls);
        Mail::assertNothingSent();
        $mailer = \Mockery::mock(Factory::class);
        $mailer->shouldReceive('mailer')->once()->andReturnSelf();
        $mailer->shouldReceive('to')->once()->andReturnSelf();
        $mailer->shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP delivery result unknown'));
        $this->app->instance(Factory::class, $mailer);
        $service = $this->app->make(MailDeliveryService::class);
        $data = new MailDeliveryData($user['id'], 'inline-uncertain', 'diagnostic', 'Test', 'synthetic', now()->addHour());
        $row = $service->enqueue($data)->fresh();
        $this->assertSame('uncertain', $row->status);
        $this->assertNull($row->payload);
        $service->enqueue($data);
        $service->deliver($row->id);
        $this->assertSame(1, $connection->calls);
        Queue::assertNothingPushed();
    }

    /** 真HTTP回應取commit後狀態；失敗註冊仍可重載pending而不是重建帳號
     * @return void
     */
    public function test_registration_exposes_only_its_committed_delivery_status(): void
    {
        [, $connection] = $this->mailFixture();
        $this->rawRegistration = true;
        config(['delivery.mode' => 'inline']);
        $connection->fail = true;
        $email = 'inline-'.Str::uuid().'@example.test';
        $response = $this->postJson('/api/auth/register', ['name' => 'Inline', 'email' => $email, 'password' => 'synthetic-inline-password', 'workspaceName' => 'Inline'])->assertOk();
        $response->assertJsonPath('data.verificationRequired', true)->assertJsonPath('data.deliveryStatus', 'failed');
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.email', $email)->assertJsonPath('data.deliveryStatus', 'failed');
        $this->assertSame(1, MailDelivery::where('user_id', User::where('email', $email)->value('id'))->count());
        $this->postJson('/api/auth/resend-verification', ['email' => null])->assertStatus(400);
        $this->travel(61)->seconds();
        $connection->fail = false;
        $this->postJson('/api/auth/resend-verification', ['email' => null])->assertOk()->assertJsonPath('data.deliveryStatus', 'sent');
        $this->assertSame(2, MailDelivery::where('user_id', User::where('email', $email)->value('id'))->count());
        Queue::assertNothingPushed();
    }
}
