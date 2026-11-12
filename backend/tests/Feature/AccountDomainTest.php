<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** 帳號領域流程可在非HTTP環境執行，登入狀態只由HTTP層建立。 */
class AccountDomainTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 註冊與密碼驗證不偷偷寫Auth／Session，並服從呼叫者交易。
     *
     * @return void
     */
    public function test_account_operations_do_not_establish_http_authentication(): void
    {
        $accounts = app(AccountService::class);
        $input = ['email' => 'domain-account@example.test', 'name' => '領域帳號', 'password' => 'domain-test-password', 'workspaceName' => '領域空間'];
        $created = DB::transaction(fn () => $accounts->register($input));
        $this->assertFalse(Auth::check());
        $this->assertSame($input['email'], $created->user->email);
        $login = DB::transaction(fn () => $accounts->login($input));
        $this->assertFalse(Auth::check());
        $this->assertSame($created->workspaceId, $login->workspaceId);
        $this->assertSame($created->user->id, $accounts->context($login->user, $login->workspaceId)['user']['id']);
        try {
            DB::transaction(function () use ($accounts) {
                $accounts->register(['email' => 'rollback-domain@example.test', 'name' => '回滾帳號', 'password' => 'domain-test-password', 'workspaceName' => '回滾空間']);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $error) {
            $this->assertSame('rollback', $error->getMessage());
        }
        $this->assertFalse(User::where('email', 'rollback-domain@example.test')->exists());
        $this->assertFalse(Auth::check());
    }
}
