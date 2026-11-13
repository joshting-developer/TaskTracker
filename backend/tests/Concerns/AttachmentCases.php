<?php

namespace Tests\Concerns;

use App\Domain\DomainError;
use App\Models\Attachment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Repositories\AttachmentRepository;
use App\Services\AttachmentCleanupService;
use App\Services\AttachmentStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** 真HTTP與fake私有disk, 不連正式Bucket */
trait AttachmentCases
{
    /** 只核對本測試操作者資料, 不假設隔離DB沒有其他合成資料
     * @return Builder 本人附件
     */
    private function attachmentRows(): Builder
    {
        return Attachment::where('uploaded_by', Auth::id());
    }

    /** 上傳、綁定、下載、冪等與刪除保存
     * @return void
     */
    public function test_private_attachment_lifecycle_and_idempotency(): void
    {
        Storage::fake('attachments');
        $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => Str::uuid().'@example.test', 'password' => 'attachment-test-password', 'workspaceName' => 'Images'])->assertOk();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Images', 'code' => 'IMG', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Image card', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $base = '/api/v1/projects/'.$project['id'].'/attachments';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        $this->getJson($base.'/limits')->assertOk()->assertJsonPath('data.maxBytes', 10485760)->assertJsonPath('data.perIssue', 20);
        $request = (string) Str::uuid();
        $upload = fn () => $this->post($base, ['requestId' => $request, 'file' => UploadedFile::fake()->createWithContent('screen.png', $png)], ['Accept' => 'application/json']);
        $attachment = $upload()->assertOk()->json('data');
        $this->assertSame('pending', $attachment['status']);
        $this->assertSame('attachment:'.$attachment['id'], $attachment['reference']);
        $this->assertArrayNotHasKey('storage_key', $attachment);
        $this->assertSame($attachment['id'], $upload()->assertOk()->json('data.id'));
        $this->assertSame(1, $this->attachmentRows()->count());
        $content = $base.'/'.$attachment['id'].'/content';
        $this->get($content)->assertOk()->assertHeader('content-type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($png);
        $input = ['requestId' => (string) Str::uuid(), 'expectedRevision' => 1, 'issueId' => $issue['id'], 'issueRevision' => 1];
        $this->postJson($base.'/'.$attachment['id'].'/bind', [...$input, 'expectedRevision' => '1'])->assertStatus(400);
        $bound = $this->postJson($base.'/'.$attachment['id'].'/bind', $input)->assertOk()->assertJsonPath('data.issueRevision', 2)->json('data');
        $replayed = $this->postJson($base.'/'.$attachment['id'].'/bind', $input)->assertOk()->json('data');
        ksort($bound);
        ksort($replayed);
        $this->assertSame($bound, $replayed);
        $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'].'/attachments')->assertOk()->assertJsonCount(1, 'data.items');
        $row = $this->attachmentRows()->firstOrFail();
        Storage::disk('attachments')->assertExists($row->storage_key);
        $this->deleteJson($base.'/'.$attachment['id'], ['requestId' => (string) Str::uuid(), 'expectedRevision' => 2, 'issueId' => $issue['id'], 'issueRevision' => 2])->assertOk()->assertJsonPath('data.status', 'deleted');
        $this->get($content)->assertStatus(404);
        Storage::disk('attachments')->assertExists($row->storage_key);
        $this->travel(31)->days();
        $cleaned = app(AttachmentCleanupService::class)->run();
        $this->assertGreaterThanOrEqual(1, $cleaned['purged']);
        $this->assertSame(0, $cleaned['failed']);
        Storage::disk('attachments')->assertMissing($row->storage_key);
        $this->assertSame(['purged' => 0, 'failed' => 0], app(AttachmentCleanupService::class)->run());
        $this->travelBack();
    }

    /** 偽造內容、大小與配額拒絕, 失敗不留metadata
     * @return void
     */
    public function test_attachment_content_validation_and_quotas(): void
    {
        Storage::fake('attachments');
        $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => Str::uuid().'@example.test', 'password' => 'attachment-test-password', 'workspaceName' => 'Images'])->assertOk();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Images', 'code' => 'IMG', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $url = '/api/v1/projects/'.$project['id'].'/attachments';
        foreach (['image.png' => '<html>executable</html>', 'image.svg' => '<svg/>', 'fake.jpg' => '<?php echo 1;'] as $name => $content) {
            $this->post($url, ['requestId' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent($name, $content)], ['Accept' => 'application/json'])->assertStatus(400);
        }
        $this->assertSame(0, $this->attachmentRows()->count());
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        config(['attachments.disk' => 'public']);
        $this->post($url, ['requestId' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('screen.png', $png)], ['Accept' => 'application/json'])->assertStatus(502);
        config(['attachments.disk' => 'attachments']);
        config(['attachments.max_bytes' => 2]);
        $this->post($url, ['requestId' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('screen.png', str_repeat('x', 10))], ['Accept' => 'application/json'])->assertStatus(400);
        $this->assertSame(0, $this->attachmentRows()->count());
    }

    /** 其他使用者不能看pending, 撤權後連既有連結與重播都拒絕
     * @return void
     */
    public function test_attachment_workspace_project_actor_and_revocation_boundaries(): void
    {
        Storage::fake('attachments');
        $register = fn ($name) => $this->postJson('/api/auth/register', ['name' => $name, 'email' => Str::uuid().'@example.test', 'password' => 'attachment-test-password', 'workspaceName' => $name])->assertOk()->json('data');
        $owner = $register('Owner');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Images', 'code' => 'IMG', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $otherProject = $this->postJson('/api/rpc/createProject', ['name' => 'Other', 'code' => 'OTHER', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Image card', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $base = '/api/v1/projects/'.$project['id'].'/attachments';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        $request = (string) Str::uuid();
        $attachment = $this->post($base, ['requestId' => $request, 'file' => UploadedFile::fake()->createWithContent('screen.png', $png)], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->post($base, ['requestId' => $request, 'file' => UploadedFile::fake()->createWithContent('renamed.png', $png)], ['Accept' => 'application/json'])->assertStatus(409);
        $this->get('/api/v1/projects/'.$otherProject['id'].'/attachments/'.$attachment['id'].'/content')->assertStatus(404);
        $input = ['requestId' => (string) Str::uuid(), 'expectedRevision' => 1, 'issueId' => $issue['id'], 'issueRevision' => 1];
        $memberId = (string) Str::uuid();
        User::create(['id' => $memberId, 'name' => 'Member', 'email' => Str::uuid().'@example.test', 'password' => bcrypt('attachment-test-password')]);
        $projectModel = Project::where('public_id', $project['id'])->firstOrFail();
        $memberKey = DB::table('workspace_members')->insertGetId(['workspace_id' => $projectModel->workspace_id, 'user_id' => $memberId, 'role' => 'manager']);
        $this->actingAs(User::findOrFail($memberId))->withSession(['workspace_id' => $owner['workspace']['id']]);
        $content = $base.'/'.$attachment['id'].'/content';
        $this->get($content)->assertStatus(404);
        $this->postJson($base.'/'.$attachment['id'].'/bind', $input)->assertStatus(404);
        $this->actingAs(User::findOrFail($owner['user']['id']));
        $this->postJson($base.'/'.$attachment['id'].'/bind', [...$input, 'issueRevision' => 999])->assertStatus(409);
        $this->postJson($base.'/'.$attachment['id'].'/bind', $input)->assertOk();
        $this->actingAs(User::findOrFail($memberId));
        $this->get($content)->assertOk();
        $this->postJson($base.'/'.$attachment['id'].'/bind', [...$input, 'expectedRevision' => 2, 'issueRevision' => 2])->assertStatus(400);
        DB::table('workspace_members')->where('id', $memberKey)->update(['role' => 'user']);
        $this->get($content)->assertStatus(404);
        $this->actingAs(User::findOrFail($owner['user']['id']));
        $this->postJson('/api/rpc/deleteIssue', ['id' => $issue['id'], 'projectId' => $project['id'], 'expectedRevision' => 2, 'requestId' => (string) Str::uuid()])->assertStatus(409);
        WorkspaceMember::where('user_id', $owner['user']['id'])->update(['disabled_at' => now()]);
        $this->postJson($base.'/'.$attachment['id'].'/bind', $input)->assertStatus(401);
        Auth::logout();
        $other = $register('Other tenant');
        $this->get($content)->assertStatus(404);
        $this->postJson($base.'/'.$attachment['id'].'/bind', $input)->assertStatus(404);
    }

    /** IO故障能以相同requestId恢復, pending與刪除配額不能繞過
     * @return void
     */
    public function test_upload_failure_retry_quota_and_expired_cleanup(): void
    {
        Storage::fake('attachments');
        $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => Str::uuid().'@example.test', 'password' => 'attachment-test-password', 'workspaceName' => 'Retry'])->assertOk();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Retry', 'code' => 'RETRY', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $url = '/api/v1/projects/'.$project['id'].'/attachments';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        $key = (string) Str::uuid();
        $put = fn ($id) => $this->post($url, ['requestId' => $id, 'file' => UploadedFile::fake()->createWithContent('screen.png', $png)], ['Accept' => 'application/json']);
        $fault = new class(app(FilesystemManager::class)) extends AttachmentStorage
        {
            public bool $fail = true;

            public function put(string $disk, string $key, string $path): void
            {
                if ($this->fail) {
                    DomainError::fail('STORAGE_ERROR', 'synthetic storage failure');
                }
                parent::put($disk, $key, $path);
            }
        };
        $this->app->instance(AttachmentStorage::class, $fault);
        $put($key)->assertStatus(502);
        $this->assertSame('uploading', $this->attachmentRows()->firstOrFail()->status);
        $fault->fail = false;
        $put($key)->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertSame(1, $this->attachmentRows()->count());
        $this->get($url.'/'.$this->attachmentRows()->firstOrFail()->public_id.'/content', ['X-Workspace-ID' => 'different-workspace'])->assertStatus(409);
        config(['attachments.pending_per_user' => 1]);
        $put((string) Str::uuid())->assertStatus(400);
        config(['attachments.pending_per_user' => 20, 'attachments.workspace_bytes' => strlen($png)]);
        $put((string) Str::uuid())->assertStatus(400);
        $row = $this->attachmentRows()->firstOrFail();
        $this->travel(25)->hours();
        $this->get($url.'/'.$row->public_id.'/content')->assertStatus(404);
        $put($key)->assertStatus(404);
        $cleaned = app(AttachmentCleanupService::class)->run();
        $this->assertGreaterThanOrEqual(1, $cleaned['purged']);
        $this->assertSame(0, $cleaned['failed']);
        $this->travelBack();
    }

    /** 清理失敗可重跑, 不會把失敗物件當已清除或釋放配額
     * @return void
     */
    public function test_cleanup_storage_failure_remains_retryable(): void
    {
        Storage::fake('attachments');
        $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => Str::uuid().'@example.test', 'password' => 'attachment-test-password', 'workspaceName' => 'Cleanup'])->assertOk();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Cleanup', 'code' => 'CLEAN', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        $this->post('/api/v1/projects/'.$project['id'].'/attachments', ['requestId' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('screen.png', $png)], ['Accept' => 'application/json'])->assertOk();
        $row = $this->attachmentRows()->firstOrFail();
        $fault = new class(app(FilesystemManager::class)) extends AttachmentStorage
        {
            public bool $fail = true;

            public function delete(string $disk, string $key): bool
            {
                return $this->fail ? false : parent::delete($disk, $key);
            }
        };
        $cleanup = new AttachmentCleanupService(app(AttachmentRepository::class), $fault);
        $this->travel(25)->hours();
        $cleaned = $cleanup->run();
        $this->assertGreaterThanOrEqual(1, $cleaned['failed']);
        $this->assertSame('purging', $row->fresh()->status);
        Storage::disk('attachments')->assertExists($row->storage_key);
        $fault->fail = false;
        $cleaned = $cleanup->run();
        $this->assertGreaterThanOrEqual(1, $cleaned['purged']);
        $this->assertSame(0, $cleaned['failed']);
        Storage::disk('attachments')->assertMissing($row->storage_key);
        $this->travelBack();
    }

    /** 列表只讀指定Issue, 超過每卡上限不產生半綁定狀態
     * @return void
     */
    public function test_attachment_list_scope_limit_and_pixels(): void
    {
        Storage::fake('attachments');
        $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => Str::uuid().'@example.test', 'password' => 'attachment-test-password', 'workspaceName' => 'Scope'])->assertOk();
        $rpc = fn ($operation, $input) => $this->postJson('/api/rpc/'.$operation, ['requestId' => (string) Str::uuid(), ...$input])->assertOk()->json('data');
        $project = $rpc('createProject', ['name' => 'Scope', 'code' => 'SCOPE']);
        $issue = $rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Target']);
        $other = $rpc('createIssue', ['projectId' => $project['id'], 'title' => 'Unrelated']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        $url = '/api/v1/projects/'.$project['id'].'/attachments';
        $put = fn () => $this->post($url, ['requestId' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('screen.png', $png)], ['Accept' => 'application/json']);
        config(['attachments.max_pixels' => 0]);
        $put()->assertStatus(400);
        $this->assertSame(0, $this->attachmentRows()->count());
        config(['attachments.max_pixels' => 25000000]);
        $first = $put()->assertOk()->json('data');
        $second = $put()->assertOk()->json('data');
        $this->postJson($url.'/'.$first['id'].'/bind', ['requestId' => (string) Str::uuid(), 'expectedRevision' => 1, 'issueId' => $issue['id'], 'issueRevision' => 1])->assertOk();
        config(['attachments.per_issue' => 1]);
        $this->postJson($url.'/'.$second['id'].'/bind', ['requestId' => (string) Str::uuid(), 'expectedRevision' => 1, 'issueId' => $issue['id'], 'issueRevision' => 2])->assertStatus(400);
        $this->assertSame('pending', Attachment::where('public_id', $second['id'])->firstOrFail()->status);
        $template = Attachment::where('public_id', $first['id'])->firstOrFail()->getAttributes();
        unset($template['id']);
        $otherId = Issue::where('public_id', $other['id'])->value('id');
        for ($n = 0; $n < 110; $n++) {
            DB::table('attachments')->insert([...$template, 'public_id' => (string) Str::uuid(), 'upload_request_id' => (string) Str::uuid(), 'issue_id' => $otherId]);
        }
        $response = $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$issue['id'].'/attachments?perPage=100')->assertOk()->assertJsonPath('data.total', 1)->assertJsonCount(1, 'data.items');
        $this->assertLessThan(1200, strlen($response->getContent()));
        $this->getJson('/api/v1/projects/'.$project['id'].'/issues/'.$other['id'].'/attachments?page=2')->assertOk()->assertJsonCount(20, 'data.items')->assertJsonPath('data.total', 110);
    }

    /** 物件已寫入但finalize或bind失敗時, 保留可重試狀態且DB不半完成
     * @return void
     */
    public function test_finalize_and_bind_failures_preserve_recoverable_state(): void
    {
        Storage::fake('attachments');
        $fault = new class extends AttachmentRepository
        {
            public bool $failFinalize = true;

            public bool $failBind = true;

            public function save(Attachment $attachment, array $attributes): Attachment
            {
                if ($this->failFinalize && ($attributes['status'] ?? '') === 'pending') {
                    DomainError::fail('CONFLICT', 'synthetic finalize failure');
                }

                return parent::save($attachment, $attributes);
            }

            public function touchIssue(Issue $issue, array $event): void
            {
                parent::touchIssue($issue, $event);
                if ($this->failBind) {
                    DomainError::fail('CONFLICT', 'synthetic bind failure');
                }
            }
        };
        $this->app->instance(AttachmentRepository::class, $fault);
        $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => Str::uuid().'@example.test', 'password' => 'attachment-test-password', 'workspaceName' => 'Recovery'])->assertOk();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Recovery', 'code' => 'RECOVER', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Recovery', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        $url = '/api/v1/projects/'.$project['id'].'/attachments';
        $key = (string) Str::uuid();
        $put = fn () => $this->post($url, ['requestId' => $key, 'file' => UploadedFile::fake()->createWithContent('screen.png', $png)], ['Accept' => 'application/json']);
        $put()->assertStatus(409);
        $row = $this->attachmentRows()->firstOrFail();
        $this->assertSame('uploading', $row->status);
        Storage::disk('attachments')->assertExists($row->storage_key);
        $fault->failFinalize = false;
        $put()->assertOk()->assertJsonPath('data.id', $row->public_id);
        $input = ['requestId' => (string) Str::uuid(), 'expectedRevision' => 1, 'issueId' => $issue['id'], 'issueRevision' => 1];
        $this->postJson($url.'/'.$row->public_id.'/bind', $input)->assertStatus(409);
        $this->assertSame('pending', $row->fresh()->status);
        $this->assertSame(1, Issue::where('public_id', $issue['id'])->value('revision'));
        $fault->failBind = false;
        $this->postJson($url.'/'.$row->public_id.'/bind', $input)->assertOk()->assertJsonPath('data.issueRevision', 2);
        $this->assertSame(1, $this->attachmentRows()->count());
    }
}
