<?php

namespace Tests\Concerns;

use App\Models\EmailVerification;
use App\Models\MailDelivery;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Services\MailConnectionService;
use App\Services\MailDeliveryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** 純隔離合成註冊與假寄信, 不向真SMTP寄送 */
trait EmailVerificationCases
{
    /** 建立隔離待驗證帳號, 只在測試記憶體讀取驗證資訊
     * @return array{string,string,string} 帳號ID、已退役碼測試值與連結token */
    private function pendingAccount(): array
    {
        $this->rawRegistration = true;
        Mail::fake();
        Queue::fake();
        config(['app.url' => 'https://tasktracker.example.test', 'delivery.connection' => 'redis']);
        $email = Str::uuid().'@example.test';
        $this->postJson('/api/auth/register', ['name' => 'Verify', 'email' => $email, 'password' => 'isolated-verification', 'workspaceName' => 'Verify'])->assertOk()->assertJsonPath('data.verificationRequired', true)->assertJsonMissingPath('data.user');
        $user = User::where('email', $email)->firstOrFail();
        $mail = MailDelivery::where('user_id', $user->id)->where('kind', 'verification')->firstOrFail();
        $this->assertStringNotContainsString('驗證碼', $mail->payload['body']);
        preg_match('~/activate/([a-f0-9]{64})~', $mail->payload['body'], $token);

        return [$user->id, 'RETIRED', $token[1]];
    }

    /** Pending registration has no workspace access and requires an activation link
     * @return void */
    public function test_pending_registration_requires_single_use_activation_link(): void
    {
        [$id,$code,$token] = $this->pendingAccount();
        $this->assertGuest();
        $pending = $this->postJson('/api/auth/current')->assertJsonPath('data.verificationRequired', true)->json('data');
        $spec = json_decode(file_get_contents(resource_path('auth-session-openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $keys = $spec['components']['schemas']['PendingVerification']['required'];
        sort($keys);
        $actual = array_keys($pending);
        sort($actual);
        $this->assertSame($keys, $actual);
        $this->assertIsInt($pending['resendAfterSeconds']);
        $this->postJson('/api/auth/verify-email', ['code' => $code, 'token' => str_repeat('a', 64)])->assertStatus(400);
        $this->getJson('/api/v1/projects')->assertStatus(401);
        $row = EmailVerification::where('user_id', $id)->firstOrFail();
        $this->assertNotSame($code, $row->code_hash);
        $this->postJson('/api/auth/verify-email', ['code' => strtolower($code)])->assertStatus(400);
        $this->postJson('/api/auth/verify-email', ['token' => $token])->assertOk()->assertJsonPath('data.user.id', $id);
        $this->assertNotNull(User::findOrFail($id)->email_verified_at);
        $this->postJson('/api/auth/verify-email', ['code' => $code])->assertStatus(400);
    }

    /**
     * 原pending Session在另一分頁啟用後可重驗恢復, 過期不恢復
     *
     * @return void
     */
    public function test_pending_session_recovers_only_its_activated_unexpired_account(): void
    {
        [$id, , $token] = $this->pendingAccount();
        $this->postJson('/api/auth/verify-email', ['token' => $token])->assertOk();
        $this->postJson('/api/auth/logout')->assertOk();
        Auth::forgetGuards();
        $this->withSession(['pending_user_id' => $id]);
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.user.id', $id)->assertJsonMissingPath('data.verificationRequired');
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/current')->assertStatus(401);
        $this->travel(31)->minutes();
        $this->withSession(['pending_user_id' => $id]);
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.verificationRequired', true);
        $this->assertGuest();
    }

    /**
     * 啟用別人的連結不會提升原本未啟用的Session
     *
     * @return void
     */
    public function test_pending_session_cannot_recover_another_activated_user(): void
    {
        [$first] = $this->pendingAccount();
        $this->postJson('/api/auth/logout')->assertOk();
        [$second, , $token] = $this->pendingAccount();
        $this->postJson('/api/auth/verify-email', ['token' => $token])->assertOk()->assertJsonPath('data.user.id', $second);
        $this->postJson('/api/auth/logout')->assertOk();
        Auth::forgetGuards();
        $this->withSession(['pending_user_id' => $first]);
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.verificationRequired', true)->assertJsonMissingPath('data.user');
        $this->assertGuest();
        $this->assertNull(User::findOrFail($first)->email_verified_at);
    }

    /** test link get has no side effect and post consumes both credentials
     * @return void */
    public function test_link_get_has_no_side_effect_and_post_consumes_both_credentials(): void
    {
        [$id,$code,$token] = $this->pendingAccount();
        $this->get('/app/#/activate/'.$token);
        $this->assertNull(User::findOrFail($id)->email_verified_at);
        $this->postJson('/api/auth/verify-email', ['token' => $token])->assertOk();
        $this->postJson('/api/auth/verify-email', ['code' => $code])->assertStatus(400);
        $this->postJson('/api/auth/verify-email', ['token' => $token])->assertStatus(400);
        $this->assertNull(MailDelivery::where('user_id', $id)->first()->payload);
    }

    /** Retired codes cannot consume or invalidate a valid activation link
     * @return void */
    public function test_retired_codes_do_not_consume_activation_link(): void
    {
        [$id,$code,$token] = $this->pendingAccount();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/verify-email', ['code' => 'WRONGXX'])->assertStatus(400);
        }
        $this->assertSame(0, EmailVerification::where('user_id', $id)->first()->attempts);
        $this->postJson('/api/auth/verify-email', ['code' => $code])->assertStatus(400);
        $this->postJson('/api/auth/verify-email', ['token' => $token])->assertOk();
        $this->assertNotNull(User::findOrFail($id)->email_verified_at);
    }

    /** test expiry rejects real code link and local shortcut
     * @return void */
    public function test_expiry_rejects_real_code_link_and_local_shortcut(): void
    {
        [$id,$code,$token] = $this->pendingAccount();
        $this->travel(31)->minutes();
        foreach ([['code' => $code], ['token' => $token], ['code' => 'local666']] as $input) {
            $this->postJson('/api/auth/verify-email', $input)->assertStatus(400);
        }
        $this->assertNull(User::findOrFail($id)->email_verified_at);
    }

    /** test resend cooldown replaces old challenge and pending mail
     * @return void */
    public function test_resend_cooldown_replaces_old_challenge_and_pending_mail(): void
    {
        [$id,$code,$token] = $this->pendingAccount();
        $old = EmailVerification::where('user_id', $id)->firstOrFail();
        $this->postJson('/api/auth/resend-verification', ['requestId' => (string) Str::uuid()])->assertStatus(400);
        $this->travel(61)->seconds();
        $this->postJson('/api/auth/resend-verification', ['requestId' => (string) Str::uuid()])->assertOk();
        $this->assertNotSame($old->id, EmailVerification::where('user_id', $id)->first()->id);
        $this->assertSame('skipped', MailDelivery::where('verification_id', $old->id)->first()->status);
        $this->postJson('/api/auth/verify-email', ['token' => $token])->assertStatus(400);
        $this->postJson('/api/auth/verify-email', ['code' => 'local666'])->assertOk();
    }

    /** test change email checks unique and login returns pending until verified
     * @return void */
    public function test_change_email_checks_unique_and_login_returns_pending_until_verified(): void
    {
        [$id] = $this->pendingAccount();
        $oldEmail = User::findOrFail($id)->email;
        Auth::logout();
        $this->flushSession();
        $this->postJson('/api/auth/login', ['email' => $oldEmail, 'password' => 'isolated-verification'])->assertOk()->assertJsonPath('data.verificationRequired', true);
        $this->assertGuest();
        $this->travel(61)->seconds();
        $new = Str::uuid().'@example.test';
        $this->postJson('/api/auth/resend-verification', ['email' => $new])->assertOk()->assertJsonPath('data.email', $new);
        $this->assertSame($new, User::findOrFail($id)->email);
        $this->postJson('/api/auth/verify-email', ['code' => 'local666'])->assertOk();
    }

    /** test local shortcut rejected in production and staging
     * @return void */
    public function test_local_shortcut_rejected_in_production_and_staging(): void
    {
        [$id] = $this->pendingAccount();
        foreach (['production', 'staging'] as $env) {
            config(['app.env' => $env]);
            $this->postJson('/api/auth/verify-email', ['code' => 'local666'])->assertStatus(400);
        }
        $this->assertNull(User::findOrFail($id)->email_verified_at);
        config(['app.env' => 'local']);
        $this->postJson('/api/auth/verify-email', ['code' => 'local666'])->assertOk();
    }

    /** test resend requires pending session and forged user is ignored
     * @return void */
    public function test_resend_requires_pending_session_and_forged_user_is_ignored(): void
    {
        [$id] = $this->pendingAccount();
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/resend-verification', ['userId' => $id])->assertStatus(401);
        $this->postJson('/api/auth/verify-email', ['userId' => $id, 'code' => 'local666'])->assertStatus(400);
    }

    /** 既有帳號不補驗證, 相同信箱仍不能重複註冊
     * @return void
     */
    public function test_existing_account_does_not_require_verification_or_allow_duplicate_registration(): void
    {
        [$id] = $this->pendingAccount();
        $user = User::findOrFail($id);
        $user->forceFill(['email_verification_required' => false, 'email_verified_at' => null])->save();
        Auth::logout();
        $this->flushSession();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'isolated-verification'])->assertOk()->assertJsonPath('data.user.id', $id)->assertJsonMissingPath('data.verificationRequired');
        $this->assertNull($user->fresh()->email_verified_at, 'Legacy verification time is not fabricated');
        $this->postJson('/api/auth/register', ['name' => 'Duplicate', 'email' => $user->email, 'password' => 'isolated-verification', 'workspaceName' => 'Other'])->assertStatus(409);
    }

    /** 重寄在SMTP預連線期間撤銷舊信, 舊Worker不得送出
     * @return void
     */
    public function test_resend_during_smtp_preflight_cancels_old_delivery(): void
    {
        [$id] = $this->pendingAccount();
        $old = MailDelivery::where('user_id', $id)->where('kind', 'verification')->firstOrFail();
        $this->travel(61)->seconds();
        $connection = new class(Mail::getFacadeRoot()) extends MailConnectionService
        {
            public ?\Closure $onConnect = null;

            /** 測試連線只呼叫注入的競態操作
             * @return void
             */
            public function connect(): void
            {
                ($this->onConnect)();
            }
        };
        $connection->onConnect = fn () => app(EmailVerificationService::class)->resend($id, null);
        $this->app->instance(MailConnectionService::class, $connection);
        $this->app->forgetInstance(MailDeliveryService::class);
        app(MailDeliveryService::class)->deliver($old->id);
        $this->assertSame('skipped', $old->fresh()->status);
        Mail::assertNothingSent();
    }
}
