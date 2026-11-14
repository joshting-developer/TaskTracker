<?php

namespace Tests\Concerns;

use App\Mail\TransactionalMessage;
use App\Models\MailDelivery;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Services\MailDeliveryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** 邀請寄信、重試、成員轉移與查詢只用隔離人造資料 */
trait WorkspaceInvitationCases
{
    use MailFixture;

    /**
     * 建立新地址邀請, 回傳原HTTP輸入以驗證重播
     *
     * @return array{array,array,MailDelivery,object} 輸入、結果、信件及假連線
     */
    private function invitationFixture(): array
    {
        [, $connection] = $this->mailFixture();
        config(['app.url' => 'https://tasktracker.example.test']);
        $input = ['email' => 'invited-'.Str::uuid().'@example.test', 'requestId' => (string) Str::uuid()];
        $result = $this->postJson('/api/rpc/inviteMember', $input)->assertOk()->json('data');
        $row = MailDelivery::where('kind', 'invitation')->where('invitation_id', WorkspaceInvitation::where('public_id', hash('sha256', $result['token']))->value('id'))->firstOrFail();

        return [$input, $result, $row, $connection];
    }

    /**
     * 新地址能寄邀請, 同操作只建一份且token不以明碼落在outbox或receipt
     *
     * @return void
     */
    public function test_invitation_mail_supports_new_email_encrypted_replay_and_one_delivery(): void
    {
        [$input, $result, $row] = $this->invitationFixture();
        $this->assertFalse(User::where('email', $input['email'])->exists());
        $this->assertSame($input['email'], $row->recipient);
        $this->assertStringContainsString('/app/#/invite?token='.$result['token'], $row->payload['body']);
        $this->assertStringNotContainsString($result['token'], DB::table('mail_deliveries')->where('id', $row->id)->value('payload'));
        $this->assertStringNotContainsString($input['email'], DB::table('mail_deliveries')->where('id', $row->id)->value('recipient'));
        $this->assertStringNotContainsString($result['token'], json_encode(DB::table('operation_receipts')->pluck('result')->all()));
        $this->postJson('/api/rpc/inviteMember', $input)->assertOk()->assertJsonPath('data.token', $result['token']);
        $this->assertSame(1, MailDelivery::where('invitation_id', $row->invitation_id)->count());
        $this->postJson('/api/rpc/inviteMember', [...$input, 'email' => 'other@example.test'])->assertStatus(409);
        $this->postJson('/api/rpc/inviteMember', [...$input, 'unexpected' => true])->assertStatus(409);
        $mail = $this->app->make(MailDeliveryService::class);
        $mail->deliver($row->id);
        $mail->deliver($row->id);
        Mail::assertSent(TransactionalMessage::class, 1);
        Mail::assertSent(TransactionalMessage::class, fn ($message) => $message->hasTo($input['email']));
        $this->assertSame('sent', $row->fresh()->status);
        $this->getJson('/api/v1/workspace/invitations')->assertOk()->assertJsonPath('data.items.0.deliveryStatus', 'sent')->assertJsonMissingPath('data.items.0.token');
        WorkspaceMember::where('user_id', $row->user_id)->update(['role' => 'user']);
        $this->postJson('/api/rpc/inviteMember', $input)->assertStatus(403);
        $this->getJson('/api/v1/workspace/invitations')->assertStatus(403);
    }

    /**
     * 同地址新操作撤銷舊連結, SMTP預連線後也會重驗消耗狀態
     *
     * @return void
     */
    public function test_reissue_and_consumption_before_send_prevent_old_mail(): void
    {
        [$input, $first, $old, $connection] = $this->invitationFixture();
        $next = $this->postJson('/api/rpc/inviteMember', [...$input, 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->assertNotSame($first['token'], $next['token']);
        $this->postJson('/api/auth/invitation', ['inviteToken' => $first['token']])->assertStatus(400);
        $this->getJson('/api/v1/workspace/invitations')->assertJsonPath('data.total', 1);
        $service = $this->app->make(MailDeliveryService::class);
        $service->deliver($old->id);
        $this->assertSame('skipped', $old->fresh()->status);
        $this->assertSame(0, $connection->calls);
        $current = MailDelivery::where('kind', 'invitation')->where('id', '!=', $old->id)->where('user_id', $old->user_id)->firstOrFail();
        $connection->onConnect = fn () => WorkspaceInvitation::whereKey($current->invitation_id)->update(['consumed_at' => now()]);
        $service->deliver($current->id);
        $this->assertSame('skipped', $current->fresh()->status);
        $this->getJson('/api/v1/workspace/invitations')->assertJsonPath('data.total', 0);
        Mail::assertNothingSent();
    }

    /**
     * 邀請者失去該空間資格或權限後, 不繼續替該邀請寄信
     *
     * @return void
     */
    public function test_inviter_revocation_before_smtp_cancels_delivery(): void
    {
        [, , $row, $connection] = $this->invitationFixture();
        $connection->onConnect = fn () => WorkspaceMember::where('user_id', $row->user_id)->update(['disabled_at' => now()]);
        $this->app->make(MailDeliveryService::class)->deliver($row->id);
        $this->assertSame('skipped', $row->fresh()->status);
        $this->assertNull($row->fresh()->payload);
        Mail::assertNothingSent();
    }

    /**
     * 有效邀請只驗證新帳號的指定信箱, 不再寄第二封啟用信
     *
     * @return void
     */
    public function test_invited_signup_verifies_bound_email_once_without_second_mail(): void
    {
        [$input, $invitation] = $this->invitationFixture();
        $this->postJson('/api/auth/logout')->assertOk();
        $this->rawRegistration = true;
        $signup = ['name' => 'Invited', 'email' => $input['email'], 'password' => 'isolated-invite-password', 'inviteToken' => $invitation['token']];
        $this->postJson('/api/auth/register', [...$signup, 'email' => 'changed@example.test'])->assertStatus(400);
        $this->assertFalse(User::where('email', 'changed@example.test')->exists());
        $result = $this->postJson('/api/auth/register', $signup)->assertOk()->assertJsonMissingPath('data.verificationRequired')->json('data');
        $user = User::findOrFail($result['user']['id']);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->email_verification_required);
        $this->assertSame(0, MailDelivery::where('user_id', $user->id)->where('kind', 'verification')->count());
        $this->postJson('/api/auth/current')->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/register', $signup)->assertStatus(400);
        $this->assertSame(1, User::where('email', $input['email'])->count());
        $this->postJson('/api/auth/login', ['email' => $input['email'], 'password' => 'isolated-invite-password'])->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    /**
     * 待接受列表在SQL限定工作區、期限及狀態, 有界穩定分頁且不讀秘密
     *
     * @return void
     */
    public function test_pending_invites_are_scoped_bounded_and_exclude_accepted_or_expired(): void
    {
        [$input, , $row] = $this->invitationFixture();
        $workspace = WorkspaceInvitation::findOrFail($row->invitation_id)->workspace_id;
        WorkspaceInvitation::whereKey($row->invitation_id)->update(['consumed_at' => now()]);
        $other = Workspace::create(['public_id' => (string) Str::uuid(), 'name' => 'Other invitations']);
        $records = [];
        for ($i = 0; $i < 525; $i++) {
            $records[] = ['public_id' => hash('sha256', (string) Str::uuid()), 'workspace_id' => $i < 25 ? $workspace : $other->id, 'email' => sprintf('pending-%02d@example.test', $i), 'created_at' => now(), 'expires_at' => now()->addDays(7)];
        }
        WorkspaceInvitation::insert($records);
        WorkspaceInvitation::create(['public_id' => hash('sha256', (string) Str::uuid()), 'workspace_id' => $workspace, 'email' => 'expired@example.test', 'created_at' => now()->subDays(8), 'expires_at' => now()->subDay()]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $this->getJson('/api/v1/workspace/invitations?perPage=20')->assertOk()->assertJsonPath('data.total', 25)->json('data.items');
        $second = $this->getJson('/api/v1/workspace/invitations?perPage=20&page=2')->assertOk()->json('data.items');
        $this->assertCount(20, $first);
        $this->assertCount(5, $second);
        $this->assertSame([], array_intersect(array_column($first, 'id'), array_column($second, 'id')));
        $this->assertSame(['id', 'email', 'role', 'createdAt', 'expiresAt', 'deliveryStatus'], array_keys($first[0]));
        $this->assertSame(0, count(array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'payload') || str_contains($query['query'], 'legacy_attributes') || str_contains($query['query'], 'operation_receipts'))));
        $this->getJson('/api/v1/workspace/invitations?perPage=101')->assertStatus(400);
        $this->getJson('/api/v1/workspace/invitations?search=pending-2')->assertJsonPath('data.total', 5);
        $this->getJson('/api/v1/workspace/invitations?search=%25')->assertJsonPath('data.total', 0);
        if (DB::getDriverName() === 'mysql') {
            $plan = DB::select('EXPLAIN SELECT id FROM workspace_invitations WHERE workspace_id=? AND consumed_at IS NULL AND expires_at>? ORDER BY expires_at DESC,id DESC LIMIT 20', [$workspace, now()]);
            $this->assertSame('workspace_invites_pending', $plan[0]->key);
        }
    }

    /**
     * 既有帳號接受後退出待接受清單, 只增加目標工作區資格
     *
     * @return void
     */
    public function test_existing_account_acceptance_moves_invite_to_members_without_new_account(): void
    {
        [$input, $invitation, $row] = $this->invitationFixture();
        $owner = User::findOrFail($row->user_id);
        $this->postJson('/api/auth/logout')->assertOk();
        $existing = $this->postJson('/api/auth/register', ['name' => 'Existing invitee', 'email' => $input['email'], 'password' => 'isolated-invite-password', 'workspaceName' => 'Existing workspace'])->assertOk()->json('data');
        $total = User::count();
        $this->postJson('/api/auth/acceptWorkspaceInvite', ['inviteToken' => $invitation['token']])->assertOk()->assertJsonPath('data.user.id', $existing['user']['id']);
        $this->assertSame($total, User::count());
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/login', ['email' => $owner->email, 'password' => 'isolated-mail-password'])->assertOk();
        $this->getJson('/api/v1/workspace/invitations')->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/workspace/members?search=Existing')->assertJsonPath('data.items.0.name', 'Existing invitee');
    }
}
