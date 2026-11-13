<?php

namespace Tests\Concerns;

use App\Models\MailDelivery;
use App\Services\MailConnectionService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;

/** 合成收件人與可替換SMTP連線, 不寄真信 */
trait MailFixture
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
}
