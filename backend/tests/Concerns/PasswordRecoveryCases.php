<?php

namespace Tests\Concerns;

use App\Domain\DomainError;
use App\Mail\TransactionalMessage;
use App\Models\MailDelivery;
use App\Models\PasswordReset;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AccountService;
use App\Services\MailConnectionService;
use App\Services\MailDeliveryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** 隔離恢復流程與安全邊界, 只用假信與合成帳號 */
trait PasswordRecoveryCases
{
    /** 新帳號與一次性token
     *
     * @return array{User,string} 新帳號與一次性token */
    private function recoveryFixture(): array
    {
        Mail::fake();
        Queue::fake();
        config(['app.url' => 'https://tasktracker.example.test', 'delivery.mode' => 'queue', 'delivery.connection' => 'redis']);
        $user = User::factory()->create(['id' => (string) Str::uuid(), 'email' => Str::uuid().'@example.test', 'password' => 'old-isolated-password']);
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk()->assertExactJson(['ok' => true, 'data' => ['accepted' => true]]);
        $mail = MailDelivery::where('user_id', $user->id)->where('kind', 'password_reset')->firstOrFail();
        preg_match('~/reset-password/([a-f0-9]{64})~', $mail->payload['body'], $match);
        $this->assertCount(2, $match);

        return [$user, $match[1]];
    }

    /** 申請沒有帳號識別或同步SMTP, 雜湊與加密皆可驗
     *
     * @return void 申請沒有帳號識別或同步SMTP, 雜湊與加密皆可驗 */
    public function test_request_is_generic_bounded_and_stores_only_hashed_token(): void
    {
        [$user, $token] = $this->recoveryFixture();
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'missing@example.test'])->assertOk()->json();
        $known = $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk()->json();
        $this->assertSame($unknown, $known);
        $this->assertSame(1, PasswordReset::whereKey($user->email)->count());
        $this->assertSame(hash('sha256', $token), PasswordReset::whereKey($user->email)->firstOrFail()->token);
        $this->assertNotSame($token, PasswordReset::whereKey($user->email)->firstOrFail()->token);
        $this->assertStringNotContainsString($token, DB::table('mail_deliveries')->where('user_id', $user->id)->where('kind', 'password_reset')->value('payload'));
        $this->assertSame(1, MailDelivery::where('user_id', $user->id)->where('kind', 'password_reset')->count());
        Mail::assertNothingSent();
        $this->postJson('/api/auth/forgot-password', ['email' => []])->assertStatus(400);
        $this->postJson('/api/auth/forgot-password', ['email' => 'bad'])->assertStatus(400);
    }

    /** 重設一次成功並使舊密碼／舊Session失效, 不自動登入或啟用Email
     *
     * @return void 重設一次成功並使舊密碼／舊Session失效, 不自動登入或啟用Email */
    public function test_reset_changes_password_once_and_invalidates_old_sessions(): void
    {
        [$user, $token] = $this->recoveryFixture();
        $user->forceFill(['email_verified_at' => null, 'email_verification_required' => true])->save();
        DB::table('sessions')->insert(['id' => 'old-recovery-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $input = ['email' => $user->email, 'token' => $token, 'password' => 'new-isolated-password', 'password_confirmation' => 'new-isolated-password'];
        $this->postJson('/api/auth/reset-password', $input)->assertOk()->assertJsonPath('data.reset', true);
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('new-isolated-password', $fresh->password));
        $this->assertFalse(Hash::check('old-isolated-password', $fresh->password));
        $this->assertSame(1, $fresh->session_epoch);
        $this->assertNull($fresh->email_verified_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-recovery-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest();
        $this->postJson('/api/auth/reset-password', $input)->assertStatus(400);
        $this->assertSame(1, MailDelivery::where('user_id', $user->id)->where('kind', 'password_changed')->count());
        $this->expectException(DomainError::class);
        app(AccountService::class)->assertSessionVersion($fresh, 0);
    }

    /** 錯信箱／錯token／過期／確認錯誤不改密碼
     *
     * @return void 錯信箱／錯token／過期／確認錯誤不改密碼 */
    public function test_invalid_expired_and_mismatched_reset_leave_account_unchanged(): void
    {
        [$user, $token] = $this->recoveryFixture();
        $before = $user->password;
        $input = ['email' => $user->email, 'token' => $token, 'password' => 'new-isolated-password', 'password_confirmation' => 'new-isolated-password'];
        foreach ([['token' => str_repeat('a', 64)], ['email' => 'missing@example.test'], ['password_confirmation' => 'other-password'], ['password' => 'short'], ['token' => 'bad']] as $change) {
            $this->postJson('/api/auth/reset-password', [...$input, ...$change])->assertStatus(400);
        }
        PasswordReset::where('email', $user->email)->update(['created_at' => now()->subMinutes(30)]);
        $this->postJson('/api/auth/reset-password', $input)->assertStatus(400);
        $this->assertSame($before, $user->fresh()->password);
        $this->assertSame(0, $user->fresh()->session_epoch);
    }

    /** 重寄撤銷舊token及未交付信, 不讓舊outbox再寄出
     *
     * @return void 重寄撤銷舊token及未交付信, 不讓舊outbox再寄出 */
    public function test_resend_replaces_old_link_and_cancels_pending_delivery(): void
    {
        [$user, $token] = $this->recoveryFixture();
        $first = MailDelivery::where('user_id', $user->id)->where('kind', 'password_reset')->firstOrFail();
        PasswordReset::where('email', $user->email)->update(['created_at' => now()->subMinutes(2)]);
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();
        $this->assertNotSame(hash('sha256', $token), PasswordReset::whereKey($user->email)->value('token'));
        $this->assertSame('skipped', $first->fresh()->status);
        $this->assertNull($first->fresh()->payload);
        app(MailDeliveryService::class)->deliver($first->id);
        Mail::assertNothingSent();
        $this->postJson('/api/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'new-isolated-password', 'password_confirmation' => 'new-isolated-password'])->assertStatus(400);
    }

    /** 撤銷既有PAT且舊Session即使重建也不能通過版本檢查
     *
     * @return void 撤銷既有PAT且舊Session即使重建也不能通過版本檢查 */
    public function test_reset_revokes_pat_and_rejects_resurrected_session(): void
    {
        [$user, $token] = $this->recoveryFixture();
        $workspace = Workspace::create(['public_id' => (string) Str::uuid(), 'name' => 'Recovery']);
        $pat = PersonalAccessToken::forceCreate(['tokenable_type' => $user->getMorphClass(), 'tokenable_id' => $user->id, 'workspace_id' => $workspace->id, 'name' => 'recovery-test', 'token' => hash('sha256', Str::random(40)), 'abilities' => ['read'], 'expires_at' => now()->addDay(), 'issuance_request_id' => (string) Str::uuid()]);
        $this->postJson('/api/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'new-isolated-password', 'password_confirmation' => 'new-isolated-password'])->assertOk();
        $this->assertNotNull($pat->fresh()->revoked_at);
        $this->actingAs($user)->withSession(['session_epoch' => 0, 'workspace_id' => $workspace->public_id])->postJson('/api/auth/current')->assertStatus(401);
    }

    /** 正確token的假信可交付, SMTP錯誤不更改匿名申請的結果
     *
     * @return void 正確token的假信可交付, SMTP錯誤不更改匿名申請的結果 */
    public function test_delivery_uses_current_reset_and_safe_failure_state(): void
    {
        [$user, $token] = $this->recoveryFixture();
        $this->mock(MailConnectionService::class)->shouldReceive('connect')->once()->andReturnNull();
        $row = MailDelivery::where('user_id', $user->id)->where('kind', 'password_reset')->firstOrFail();
        app(MailDeliveryService::class)->deliver($row->id);
        $this->assertSame('sent', $row->fresh()->status);
        $this->assertNull($row->fresh()->payload);
        Mail::assertSent(TransactionalMessage::class, 1);
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk()->assertJsonPath('data.accepted', true);
    }

    /** 申請交易失敗仍統一回覆且不留下部分重設資料
     *
     * @return void 申請交易失敗仍統一回覆且不留下部分重設資料 */
    public function test_failed_outbox_rolls_back_without_revealing_account(): void
    {
        $user = User::factory()->create(['id' => (string) Str::uuid(), 'email' => Str::uuid().'@example.test']);
        config(['app.url' => 'https://tasktracker.example.test']);
        $this->mock(MailDeliveryService::class)->shouldReceive('enqueue')->once()->andThrow(new \RuntimeException('synthetic failure'));
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk()->assertExactJson(['ok' => true, 'data' => ['accepted' => true]]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    /** 讀取範圍不含工作區、專案、卡片或歷史, IP限流固定生效
     *
     * @return void 讀取範圍不含工作區、專案、卡片或歷史, IP限流固定生效 */
    public function test_request_reads_no_workspace_and_limits_anonymous_requests(): void
    {
        $tables = [];
        DB::listen(function ($query) use (&$tables) {
            $tables[] = strtolower($query->sql);
        });
        config(['app.url' => 'https://tasktracker.example.test']);
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.108'])->postJson('/api/auth/forgot-password', ['email' => 'missing@example.test'])->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.108'])->postJson('/api/auth/forgot-password', ['email' => 'missing@example.test'])->assertStatus(429);
        $this->assertStringNotContainsString('workspaces', implode(' ', $tables));
        $this->assertStringNotContainsString('issues', implode(' ', $tables));
        $this->assertStringNotContainsString('time_entries', implode(' ', $tables));
    }
}
