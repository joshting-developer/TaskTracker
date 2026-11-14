<?php

namespace Tests\Concerns;

use App\Models\MailDelivery;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** memory與原生MySQL共用正式邀請制契約 */
trait RegistrationPolicyCases
{
    /** 正式與staging預設關閉, local／testing開放, 無效開關不意外放行
     * @return void
     */
    public function test_registration_environment_defaults_fail_closed(): void
    {
        $keys = ['APP_ENV', 'TASKTRACKER_PUBLIC_REGISTRATION'];
        $previous = [];
        foreach ($keys as $key) {
            $previous[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        }
        try {
            foreach (['production' => false, 'staging' => false, 'local' => true, 'testing' => true] as $environment => $expected) {
                $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $environment;
                putenv('APP_ENV='.$environment);
                unset($_ENV['TASKTRACKER_PUBLIC_REGISTRATION'],$_SERVER['TASKTRACKER_PUBLIC_REGISTRATION']);
                putenv('TASKTRACKER_PUBLIC_REGISTRATION');
                $config = require base_path('config/tasktracker.php');
                $this->assertSame($expected, $config['registration']['public']);
            }
            foreach (['true' => true, 'false' => false, 'unexpected' => false] as $setting => $expected) {
                $_ENV['TASKTRACKER_PUBLIC_REGISTRATION'] = $_SERVER['TASKTRACKER_PUBLIC_REGISTRATION'] = $setting;
                putenv('TASKTRACKER_PUBLIC_REGISTRATION='.$setting);
                $config = require base_path('config/tasktracker.php');
                $this->assertSame($expected, $config['registration']['public']);
            }
        } finally {
            foreach ($previous as $key => [$env,$server,$process]) {
                if ($env === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $env;
                }if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }putenv($process === false ? $key : $key.'='.$process);
            }
        }
    }

    /** 匿名設定不查DB或建立Session, 關閉時直接API註冊無副作用
     * @return void
     */
    public function test_closed_registration_policy_is_minimal_and_denies_direct_signup(): void
    {
        $counts = [User::count(), Workspace::count(), MailDelivery::count()];
        config(['tasktracker.registration.public' => false]);
        $queries = [];
        DB::listen(function ($event) use (&$queries) {
            $queries[] = $event->sql;
        });
        $this->getJson('/api/auth/registration-policy')->assertOk()->assertExactJson(['ok' => true, 'data' => ['publicRegistration' => false]])->assertHeaderMissing('Set-Cookie');
        $this->assertSame([], $queries);
        $this->postJson('/api/auth/register', ['name' => 'Unauthorized', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-policy-password', 'workspaceName' => 'No workspace'])->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
        $this->assertSame($counts, [User::count(), Workspace::count(), MailDelivery::count()]);
    }

    /** 有效邀請證明指定信箱, 偽造邀請不能繞過或洩漏既有帳號
     * @return void
     */
    public function test_invitation_signup_verifies_email_when_public_registration_is_closed(): void
    {
        $users = User::count();
        $workspaces = Workspace::count();
        Queue::fake();
        $owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-policy-password', 'workspaceName' => 'Private'])->assertOk()->json('data');
        $email = Str::uuid().'@example.test';
        $invite = $this->postJson('/api/rpc/inviteMember', ['email' => $email, 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->postJson('/api/auth/logout')->assertOk();
        config(['tasktracker.registration.public' => false]);
        $this->rawRegistration = true;
        $this->postJson('/api/auth/register', ['name' => 'Invitee', 'email' => $email, 'password' => 'isolated-policy-password', 'inviteToken' => $invite['token']])->assertOk()->assertJsonMissingPath('data.verificationRequired')->assertJsonPath('data.user.email', $email);
        $this->assertAuthenticated();
        $this->assertSame($users + 2, User::count());
        $this->assertSame($workspaces + 1, Workspace::count());
        $this->getJson('/api/v1/projects')->assertOk();
        $this->postJson('/api/auth/register', ['name' => 'Probe', 'email' => $owner['user']['email'], 'password' => 'isolated-policy-password', 'inviteToken' => 'invalid-token'])->assertStatus(400)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->assertSame($users + 2, User::count());
    }

    /** 既有帳號可登入及接受其他工作區邀請, 不走重新註冊
     * @return void
     */
    public function test_existing_account_can_login_and_accept_another_workspace_invitation(): void
    {
        $users = User::count();
        $workspaces = Workspace::count();
        Queue::fake();
        $email = Str::uuid().'@example.test';
        $account = $this->postJson('/api/auth/register', ['name' => 'Existing', 'email' => $email, 'password' => 'isolated-policy-password', 'workspaceName' => 'First'])->assertOk()->json('data');
        $this->postJson('/api/auth/logout')->assertOk();
        $other = $this->postJson('/api/auth/register', ['name' => 'Other', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-policy-password', 'workspaceName' => 'Second'])->assertOk()->json('data');
        $invite = $this->postJson('/api/rpc/inviteMember', ['email' => $email, 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->postJson('/api/auth/logout')->assertOk();
        config(['tasktracker.registration.public' => false]);
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'isolated-policy-password', 'inviteToken' => $invite['token']])->assertOk()->assertJsonPath('data.user.id', $account['user']['id'])->assertJsonPath('data.workspace.id', $other['workspace']['id']);
        $this->assertSame($users + 2, User::count());
    }
}
