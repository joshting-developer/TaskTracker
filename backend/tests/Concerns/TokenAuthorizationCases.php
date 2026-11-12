<?php

namespace Tests\Concerns;

use App\Authorization\ProjectAccess;
use App\Data\TokenContext;
use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Http\Integrations\BearerTokenSession;
use App\Models\IntegrationAudit;
use App\Models\PersonalAccessToken;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\Integrations\IntegrationAudits;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** 共用真資料案例；測試用例直接呼叫公開API交易邊界，不新增假產品路由。 */
trait TokenAuthorizationCases
{
    use PersonalTokenCases;

    /**
     * 建立本輪獨立資格／專案與write PAT。
     *
     * @return array{array,array,array} 身分、專案及一次性Token，僅在隔離測試記憶體持有
     */
    private function integrationFixture(): array
    {
        $identity = $this->tokenRegister();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'integration', 'code' => 'INT', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');

        return [$identity, $project, $this->tokenIssue(['abilities' => ['write']])];
    }

    /**
     * 在測試中建立只有Bearer的Request，不接Session或user resolver。
     *
     * @param  string  $plain  隔離Token原文
     * @return Request 純Bearer請求
     */
    private function integrationRequest(string $plain): Request
    {
        return Request::create('/api/public/test', 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$plain]);
    }

    /**
     * 驗證失敗不會執行callback，且回傳穩定錯誤碼。
     *
     * @param  string  $code  預期DomainError
     * @param  callable  $use  可能拒絕的操作
     * @return void
     */
    private function rejects(string $code, callable $use): void
    {
        try {
            $use();
            $this->fail('應拒絕此次操作');
        } catch (DomainError $e) {
            $this->assertSame($code, $e->errorCode);
        }
    }

    /**
     * Token交易與即時Project權限取交集，失敗時來源紀錄一起回滾。
     *
     * @return void
     */
    public function test_token_transaction_intersects_live_project_permissions_and_rolls_back_audit(): void
    {
        [$identity, $project, $issued] = $this->integrationFixture();
        $boundary = app(BearerTokenSession::class);
        $request = $this->integrationRequest($issued['plainTextToken']);
        $member = WorkspaceMember::where('user_id', $identity['user']['id'])->firstOrFail();
        $member->update(['role' => 'user']);
        $projectModel = Project::where('public_id', $project['id'])->firstOrFail();
        ProjectMember::updateOrCreate(['member_id' => $member->id, 'project_id' => $projectModel->id], ['public_id' => ProjectAccess::memberId($project['id'], $identity['user']['id']), 'workspace_id' => $member->workspace_id, 'role_id' => 'maintainer']);
        $before = IntegrationAudit::count();
        $this->assertSame($identity['user']['id'], $boundary->read($request, fn (TokenContext $t) => $t->workspace->userId, $project['id']));
        $this->assertSame($before, IntegrationAudit::count());
        $boundary->write($request, 'issues.update', function () use ($projectModel) {
            $projectModel->update(['description' => 'accepted']);
        }, $project['id'], resourceId: $project['id']);
        $row = IntegrationAudit::latest('id')->firstOrFail();
        $this->assertSame('token', $row->source);
        $this->assertSame($issued['token']['id'], $row->token_id);
        $this->assertSame('tool', $row->token_name);
        $this->assertSame($identity['user']['id'], $row->actor_user_id);
        $this->assertSame(['write'], $row->abilities);
        $this->assertStringNotContainsString($issued['plainTextToken'], $row->toJson());
        $this->assertStringNotContainsString(PersonalAccessToken::findOrFail($row->token_id)->token, $row->toJson());
        $count = IntegrationAudit::count();
        $this->rejects('REVISION_CONFLICT', fn () => $boundary->write($request, 'issues.update', function () use ($projectModel) {
            Project::whereKey($projectModel->id)->update(['description' => 'rollback']);
            DomainError::fail('REVISION_CONFLICT', 'test');
        }, $project['id']));
        $this->assertSame('accepted', $projectModel->fresh()->description);
        $this->assertSame($count, IntegrationAudit::count());
        $this->app->instance(IntegrationAudits::class, new class extends IntegrationAudits
        {
            /**
             * 寫入來源後故意失敗，確認業務與稽核都不能部分提交。
             *
             * @param  WorkspaceContext  $workspace  當次資格
             * @param  string  $operation  固定用例識別
             * @param  string|null  $resourceId  目標識別
             * @param  TokenContext|null  $token  當次Token
             * @return void
             */
            public function append(WorkspaceContext $workspace, string $operation, ?string $resourceId = null, ?TokenContext $token = null): void
            {
                parent::append($workspace, $operation, $resourceId, $token);
                DomainError::fail('CONFLICT', 'audit failure');
            }
        });
        $failing = app(BearerTokenSession::class);
        $this->rejects('CONFLICT', fn () => $failing->write($request, 'issues.update', fn () => Project::whereKey($projectModel->id)->update(['description' => 'partial']), $project['id']));
        $this->assertSame('accepted', $projectModel->fresh()->description);
        $this->assertSame($count, IntegrationAudit::count());
        ProjectMember::where('member_id', $member->id)->where('project_id', $projectModel->id)->update(['role_id' => 'reader']);
        $this->rejects('FORBIDDEN', fn () => $boundary->write($request, 'issues.update', fn () => $this->fail('不得執行'), $project['id']));
        $boundary->read($request, fn () => true, $project['id']);
        ProjectMember::where('member_id', $member->id)->delete();
        $this->rejects('NOT_FOUND', fn () => $boundary->read($request, fn () => $this->fail('不得執行'), $project['id']));
        $member->update(['disabled_at' => now()]);
        $this->rejects('UNAUTHENTICATED', fn () => $boundary->read($request, fn () => $this->fail('不得執行')));
        $this->assertSame($count, IntegrationAudit::count());
    }

    /**
     * 重複使用同一邊界時，Scope、撤銷、到期與跨工作區都會重新判斷。
     *
     * @return void
     */
    public function test_token_scope_revocation_expiry_and_cross_workspace_are_fresh_on_reused_boundary(): void
    {
        [$a, $pa, $write] = $this->integrationFixture();
        $read = $this->tokenIssue();
        $boundary = app(BearerTokenSession::class);
        $this->rejects('FORBIDDEN', fn () => $boundary->write($this->integrationRequest($read['plainTextToken']), 'issues.update', fn () => $this->fail('read不能寫'), $pa['id']));
        [$b, $pb, $other] = $this->integrationFixture();
        foreach ([[$write, $a], [$other, $b], [$write, $a]] as [$token, $actor]) {
            $this->assertSame($actor['user']['id'], $boundary->identity($this->integrationRequest($token['plainTextToken']))['userId']);
        }
        $this->rejects('NOT_FOUND', fn () => $boundary->read($this->integrationRequest($write['plainTextToken']), fn () => false, $pb['id']));
        PersonalAccessToken::whereKey($write['token']['id'])->update(['revoked_at' => now()]);
        $this->rejects('UNAUTHENTICATED', fn () => $boundary->identity($this->integrationRequest($write['plainTextToken'])));
        PersonalAccessToken::whereKey($read['token']['id'])->update(['expires_at' => now()->subSecond()]);
        $this->rejects('UNAUTHENTICATED', fn () => $boundary->identity($this->integrationRequest($read['plainTextToken'])));
        $this->rejects('UNAUTHENTICATED', fn () => $boundary->identity(Request::create('/api/public/v1/me')));
        $this->assertSame($b['workspace']['id'], $boundary->identity($this->integrationRequest($other['plainTextToken']))['workspaceId']);
    }

    /**
     * 來源紀錄管理限本人Session、有上限，重試撤銷不會重複寫入。
     *
     * @return void
     */
    public function test_audit_management_is_session_scoped_bounded_and_revoke_retry_is_not_duplicated(): void
    {
        $a = $this->tokenRegister();
        $token = $this->tokenIssue();
        $this->postJson('/api/v1/personal-tokens/'.$token['token']['id'].'/revoke')->assertOk();
        $this->postJson('/api/v1/personal-tokens/'.$token['token']['id'].'/revoke')->assertOk();
        $audit = $this->getJson('/api/v1/personal-token-audits')->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.items.0.source', 'session')->assertJsonPath('data.items.0.operation', 'personalTokens.revoke');
        $this->assertStringNotContainsString($token['plainTextToken'], $audit->getContent());
        $template = IntegrationAudit::latest('id')->firstOrFail();
        for ($i = 0; $i < 22; $i++) {
            $template->replicate()->save();
        }
        $this->getJson('/api/v1/personal-token-audits')->assertOk()->assertJsonCount(20, 'data.items')->assertJsonPath('data.total', 24);
        $this->getJson('/api/v1/personal-token-audits?page=2')->assertOk()->assertJsonCount(4, 'data.items');
        $this->getJson('/api/v1/personal-token-audits?page=999')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/api/v1/personal-token-audits?page=0')->assertStatus(400);
        $b = $this->tokenRegister();
        $this->getJson('/api/v1/personal-token-audits')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/api/v1/personal-token-audits', ['X-Workspace-ID' => $a['workspace']['id']])->assertStatus(409);
        $invite = $this->postJson('/api/rpc/inviteMember', ['email' => $a['user']['email'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->actingAs(User::findOrFail($a['user']['id']))->withSession(['workspace_id' => $a['workspace']['id']]);
        $this->postJson('/api/auth/acceptWorkspaceInvite', ['inviteToken' => $invite['token']])->assertOk();
        $this->postJson('/api/auth/switchWorkspace', ['workspaceId' => $b['workspace']['id']])->assertOk();
        $this->getJson('/api/v1/personal-token-audits')->assertOk()->assertJsonCount(0, 'data.items');
    }
}
