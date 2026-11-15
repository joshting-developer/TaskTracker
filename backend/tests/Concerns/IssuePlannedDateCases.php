<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** 計畫日期的 Web／PAT 共用契約, 僅建立隔離合成資料 */
trait IssuePlannedDateCases
{
    /** 建立獨立工作區、專案與可讀寫 PAT
     * @return array{array,array,array} 身分、專案與憑證
     */
    private function plannedDateFixture(): array
    {
        Auth::forgetGuards();
        $identity = $this->postJson('/api/auth/register', ['name' => 'Date tester', 'email' => Str::uuid().'@example.test', 'password' => 'planned-date-test-password', 'workspaceName' => 'Planned dates'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Dates', 'code' => 'DATES', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'Date writer', 'abilities' => ['write'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');

        return [$identity, $project, $token];
    }

    /** 三種類型可有單端日期, 同日合法, 到期日不搬值, 列表不載計畫資料
     * @return void
     */
    public function test_web_planned_dates_are_optional_independent_and_not_in_list_projection(): void
    {
        [$identity, $project] = $this->plannedDateFixture();
        foreach (['epic', 'task', 'bug'] as $type) {
            $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => $type, 'type' => $type, 'startDate' => '2028-02-29', 'dueDate' => '2028-02-20', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
            $path = '/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'];
            $this->getJson($path)->assertOk()->assertJsonPath('data.startDate', '2028-02-29')->assertJsonPath('data.endDate', '')->assertJsonPath('data.dueDate', '2028-02-20');
            $this->postJson('/api/rpc/updateIssue', ['id' => $issue['id'], 'expectedRevision' => 1, 'endDate' => '2028-02-29', 'requestId' => (string) Str::uuid()])->assertOk();
            $this->getJson($path)->assertJsonPath('data.endDate', '2028-02-29');
        }
        $old = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'No planned dates', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$old['id'])->assertJsonPath('data.startDate', '')->assertJsonPath('data.endDate', '');
        $page = $this->getJson('/api/v1/projects/'.$project['id'].'/issues?perPage=2')->assertOk()->assertJsonCount(2, 'data.items')->json('data');
        foreach ($page['items'] as $row) {
            $this->assertArrayNotHasKey('startDate', $row);
            $this->assertArrayNotHasKey('endDate', $row);
        }
        $this->assertSame('date', DB::getSchemaBuilder()->getColumnType('issues', 'start_date'));
    }

    /** PAT 修改、清除、省略、錯誤日期、版本與重播, 仍由同一業務驗證
     * @return void
     */
    public function test_public_planned_dates_validate_merged_patch_replay_and_clear_without_changing_due_date(): void
    {
        [$identity, $project, $token] = $this->plannedDateFixture();
        $headers = ['Authorization' => 'Bearer '.$token['plainTextToken']];
        Auth::forgetGuards();
        $base = '/api/public/v1/projects/'.$project['id'].'/issues';
        $issue = $this->postJson($base, ['title' => 'PAT dates', 'endDate' => '2028-03-03', 'dueDate' => '2028-03-01', 'requestId' => (string) Str::uuid()], $headers)->assertOk()->json('data');
        $path = $base.'/'.$issue['id'];
        $patch = ['startDate' => '2028-03-02', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()];
        $this->patchJson($path, $patch, $headers)->assertOk()->assertJsonPath('data.revision', 2);
        $this->patchJson($path, $patch, $headers)->assertOk()->assertJsonPath('data.revision', 2);
        $this->getJson($path, $headers)->assertOk()->assertJsonPath('data.startDate', '2028-03-02')->assertJsonPath('data.endDate', '2028-03-03')->assertJsonPath('data.dueDate', '2028-03-01');
        foreach (['2028-03-04', '0001-01-01', '2027-02-29', '2028-3-2', '2028-03-02T00:00:00+08:00'] as $invalid) {
            $this->patchJson($path, ['startDate' => $invalid, 'expectedRevision' => 2, 'requestId' => (string) Str::uuid()], $headers)->assertStatus(400);
        }
        $this->patchJson($path, ['endDate' => '', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()], $headers)->assertStatus(409);
        $this->patchJson($path, ['startDate' => null, 'endDate' => '', 'expectedRevision' => 2, 'requestId' => (string) Str::uuid()], $headers)->assertOk();
        $this->getJson($path, $headers)->assertJsonPath('data.startDate', '')->assertJsonPath('data.endDate', '')->assertJsonPath('data.dueDate', '2028-03-01')->assertJsonPath('data.revision', 3);
        Auth::forgetGuards();
        $this->postJson('/api/auth/login', ['email' => $identity['user']['email'], 'password' => 'planned-date-test-password'])->assertOk();
        $events = $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'].'/history')->assertOk()->json('data.items');
        $fields = collect($events)->flatMap(fn ($event) => $event['changes'] ?? [])->pluck('field')->all();
        $this->assertContains('startDate', $fields);
        $this->assertContains('endDate', $fields);
    }

    /** 日期欄位不提供權限捷徑, read Token 不能改, 其他租戶不能讀
     * @return void
     */
    public function test_planned_dates_keep_token_scope_and_tenant_isolation(): void
    {
        [$identity, $project] = $this->plannedDateFixture();
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Private schedule', 'startDate' => '2028-01-01', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'Read only', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $path = '/api/public/v1/projects/'.$project['id'].'/issues/'.$issue['id'];
        Auth::forgetGuards();
        $this->patchJson($path, ['startDate' => '', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()], ['Authorization' => 'Bearer '.$token['plainTextToken']])->assertStatus(403);
        [$other, $otherProject, $otherToken] = $this->plannedDateFixture();
        Auth::forgetGuards();
        $this->getJson($path, ['Authorization' => 'Bearer '.$otherToken['plainTextToken']])->assertStatus(404);
    }
}
