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

    /** 描述與附件共用建卡交易, 重播與失敗均不留半完成
     * @return void
     */
    public function test_description_bind_is_atomic_scoped_and_replay_safe(): void
    {
        Storage::fake('attachments');
        $fault = new class extends AttachmentRepository
        {
            public bool $fail = false;

            /** 注入綁定失敗以驗證交易回滾
             * @param  Project  $project  隔離專案
             * @param  Issue  $issue  隔離卡片
             * @param  array  $ids  待綁定ID
             * @return void
             */
            public function bindDescription(Project $project, Issue $issue, array $ids): void
            {
                parent::bindDescription($project, $issue, $ids);
                if ($this->fail) {
                    DomainError::fail('CONFLICT', 'Injected failure');
                }
            }
        };
        $this->app->instance(AttachmentRepository::class, $fault);
        $this->postJson('/api/auth/register', ['name' => 'Draft', 'email' => Str::uuid().'@example.test', 'password' => 'attachment-test-password', 'workspaceName' => 'Drafts'])->assertOk();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Images', 'code' => 'IMG', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        $upload = fn () => $this->post('/api/v1/projects/'.$project['id'].'/attachments', ['requestId' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('draft.png', $png)], ['Accept' => 'application/json'])->assertOk()->json('data');
        $one = $upload();
        $input = ['projectId' => $project['id'], 'title' => 'With image', 'description' => '![Draft](attachment:'.$one['id'].')', 'requestId' => (string) Str::uuid()];
        $issue = $this->postJson('/api/rpc/createIssue', $input)->assertOk()->assertJsonPath('data.revision', 1)->json('data');
        $this->postJson('/api/rpc/createIssue', $input)->assertOk()->assertJsonPath('data.id', $issue['id']);
        $row = $this->attachmentRows()->where('public_id', $one['id'])->firstOrFail();
        $this->assertSame('active', $row->status);
        $this->assertSame(2, $row->revision);
        $this->assertNull($row->expires_at);
        $second = $upload();
        $other = $this->postJson('/api/rpc/createProject', ['name' => 'Other', 'code' => 'OTHER', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->postJson('/api/rpc/createIssue', ['projectId' => $other['id'], 'title' => 'Wrong scope', 'description' => '![Draft](attachment:'.$second['id'].')', 'requestId' => (string) Str::uuid()])->assertStatus(404);
        $this->assertSame('pending', $this->attachmentRows()->where('public_id', $second['id'])->value('status'));
        $this->assertSame(0, Issue::where('project_id', Project::where('public_id', $other['id'])->value('id'))->count());
        $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Wrong card', 'description' => $input['description'], 'requestId' => (string) Str::uuid()])->assertStatus(404);
        $pendingRow = $this->attachmentRows()->where('public_id', $second['id'])->firstOrFail();
        $pendingRow->update(['expires_at' => now()->subMinute()]);
        $expired = ['projectId' => $project['id'], 'title' => 'Expired', 'description' => '![Draft](attachment:'.$second['id'].')', 'requestId' => (string) Str::uuid()];
        $this->postJson('/api/rpc/createIssue', $expired)->assertStatus(404);
        $owner = $pendingRow->uploaded_by;
        $foreign = User::factory()->create(['id' => (string) Str::uuid()]);
        $pendingRow->update(['expires_at' => now()->addHour(), 'uploaded_by' => $foreign->id]);
        $this->postJson('/api/rpc/createIssue', [...$expired, 'requestId' => (string) Str::uuid()])->assertStatus(404);
        $pendingRow->update(['uploaded_by' => $owner]);
        $fault->fail = true;
        $draft = ['projectId' => $project['id'], 'title' => 'Atomic', 'description' => '![Draft](attachment:'.$second['id'].')', 'requestId' => (string) Str::uuid()];
        $this->postJson('/api/rpc/createIssue', $draft)->assertStatus(409);
        $this->assertSame('pending', $this->attachmentRows()->where('public_id', $second['id'])->value('status'));
        $this->assertSame(1, Issue::where('project_id', $row->project_id)->count());
        $fault->fail = false;
        $created = $this->postJson('/api/rpc/createIssue', $draft)->assertOk()->json('data');
        $this->assertSame('IMG-2', $created['identifier']);
        $this->postJson('/api/rpc/updateIssue', ['id' => $created['id'], 'expectedRevision' => 0, 'description' => $draft['description'], 'requestId' => (string) Str::uuid()])->assertStatus(409);
        $this->deleteJson('/api/v1/projects/'.$project['id'].'/attachments/'.$second['id'], ['requestId' => (string) Str::uuid(), 'expectedRevision' => 2, 'issueId' => $created['id'], 'issueRevision' => 1])->assertOk();
        $this->postJson('/api/rpc/updateIssue', ['id' => $created['id'], 'expectedRevision' => 2, 'title' => 'Unrelated edit', 'requestId' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.revision', 3);
    }

    /** 留言圖片與2000字原稿共用交易, 衝突與無效引用不留留言
     * @return void
     */
    public function test_comment_images_bind_atomically_and_preserve_raw_length_limit(): void
    {
        Storage::fake('attachments');
        $this->postJson('/api/auth/register', ['name' => 'Commenter', 'email' => Str::uuid().'@example.test', 'password' => 'attachment-test-password', 'workspaceName' => 'Comments'])->assertOk();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Images', 'code' => 'IMG', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Comment images', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        $upload = fn () => $this->post('/api/v1/projects/'.$project['id'].'/attachments', ['requestId' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('comment.png', $png)], ['Accept' => 'application/json'])->assertOk()->json('data');
        $image = $upload();
        $ref = '![comment](attachment:'.$image['id'].')';
        $input = ['id' => $issue['id'], 'expectedRevision' => 1, 'content' => str_repeat('x', 2000 - strlen($ref)).$ref, 'requestId' => (string) Str::uuid()];
        $this->postJson('/api/rpc/addComment', [...$input, 'content' => $input['content'].'x'])->assertStatus(400);
        $this->assertSame('pending', $this->attachmentRows()->where('public_id', $image['id'])->value('status'));
        $this->postJson('/api/rpc/addComment', [...$input, 'expectedRevision' => 0])->assertStatus(409);
        $this->postJson('/api/rpc/addComment', $input)->assertOk();
        $this->postJson('/api/rpc/addComment', $input)->assertOk();
        $model = Issue::where('public_id', $issue['id'])->firstOrFail();
        $this->assertSame(1, $model->comments()->count());
        $this->assertSame(2, $model->revision);
        $this->assertSame($input['content'], $model->comments()->first()->content);
        $this->assertSame('active', $this->attachmentRows()->where('public_id', $image['id'])->value('status'));
        $this->get('/api/v1/projects/'.$project['id'].'/attachments/'.$image['id'].'/content')->assertOk()->assertStreamedContent($png);
        $other = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Other', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->postJson('/api/rpc/addComment', ['id' => $other['id'], 'expectedRevision' => 1, 'content' => $ref, 'requestId' => (string) Str::uuid()])->assertStatus(404);
        $this->assertSame(0, Issue::where('public_id', $other['id'])->firstOrFail()->comments()->count());
        $this->assertSame(1, Issue::where('public_id', $other['id'])->value('revision'));
        $pending = $upload();
        $row = $this->attachmentRows()->where('public_id', $pending['id'])->firstOrFail();
        $payload = ['id' => $issue['id'], 'expectedRevision' => 2, 'content' => '![pending](attachment:'.$pending['id'].')', 'requestId' => (string) Str::uuid()];
        $row->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/rpc/addComment', $payload)->assertStatus(404);
        $row->update(['expires_at' => now()->addHour(), 'uploaded_by' => User::factory()->create(['id' => (string) Str::uuid()])->id]);
        $this->postJson('/api/rpc/addComment', $payload)->assertStatus(404);
        $this->assertSame(1, $model->comments()->count());
        $this->assertSame(2, $model->fresh()->revision);
    }

    /** 一般附件獨立限制與只下載, 不讓PDF／ZIP內嵌成Markdown圖片
     *
     * @return void
     */
    public function test_general_files_are_download_only_and_have_independent_quotas(): void
    {
        Storage::fake('attachments');
        $this->postJson('/api/auth/register', ['name' => 'Files', 'email' => Str::uuid().'@example.test', 'password' => 'attachment-test-password', 'workspaceName' => 'Files'])->assertOk();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Files', 'code' => 'FILES', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'Files', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $root = '/api/v1/projects/'.$project['id'].'/attachments';
        $pdf = "%PDF-1.7\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        $request = (string) Str::uuid();
        $upload = fn ($name, $bytes, $key = null) => $this->post($root, ['requestId' => $key ?? (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent($name, $bytes)], ['Accept' => 'application/json']);
        $file = $upload('document.pdf', $pdf, $request)->assertOk()->json('data');
        $upload('document.pdf', $pdf, $request)->assertOk()->assertJsonPath('data.id', $file['id']);
        $this->getJson($root.'/limits')->assertOk()->assertJsonPath('data.general.maxBytes', 10485760)->assertJsonPath('data.general.scanPolicy', 'download-only-structural');
        $this->postJson('/api/rpc/addComment', ['id' => $issue['id'], 'content' => '![pdf](attachment:'.$file['id'].')', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()])->assertStatus(400);
        $this->postJson($root.'/'.$file['id'].'/bind', ['issueId' => $issue['id'], 'issueRevision' => 1, 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()])->assertOk();
        $response = $this->get($root.'/'.$file['id'].'/content')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($pdf);
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $upload('fake.zip', $pdf)->assertStatus(400);
        $upload('fake.pdf', '<html>bad</html>')->assertStatus(400);
        $upload('truncated.pdf', '%PDF-1.7 no ending')->assertStatus(400);
        config(['attachments.general_max_bytes' => 1]);
        $upload('large.pdf', $pdf)->assertStatus(400);
        config(['attachments.general_max_bytes' => 10485760, 'attachments.general_workspace_bytes' => 1]);
        $upload('quota.pdf', $pdf)->assertStatus(400);
        config(['attachments.general_workspace_bytes' => 268435456, 'attachments.general_per_issue' => 1]);
        $next = $upload('other.pdf', $pdf)->assertOk()->json('data');
        $this->postJson($root.'/'.$next['id'].'/bind', ['issueId' => $issue['id'], 'issueRevision' => 2, 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()])->assertStatus(400);
        $this->assertSame('pending', $this->attachmentRows()->where('public_id', $next['id'])->value('status'));
    }

    /** ZIP只查有界目錄, 拒絕壓縮炸彈、路徑穿越、加密及symlink
     *
     * @return void
     */
    public function test_zip_structure_limits_reject_abuse_without_extracting(): void
    {
        Storage::fake('attachments');
        $this->postJson('/api/auth/register', ['name' => 'Zip', 'email' => Str::uuid().'@example.test', 'password' => 'attachment-test-password', 'workspaceName' => 'Archives'])->assertOk();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Files', 'code' => 'ZIP', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $make = function (string $name, string $content, bool $encrypted = false, bool $link = false): string {
            $path = tempnam(sys_get_temp_dir(), 'tt-zip-');
            try {
                $zip = new \ZipArchive;
                $this->assertTrue($zip->open($path, \ZipArchive::OVERWRITE) === true);
                $zip->addFromString($name, $content);
                if ($encrypted) {
                    $zip->setEncryptionName($name, \ZipArchive::EM_AES_256, 'isolated-password');
                }
                if ($link) {
                    $zip->setExternalAttributesName($name, \ZipArchive::OPSYS_UNIX, 0120777 << 16);
                }
                $zip->close();

                return file_get_contents($path);
            } finally {
                unlink($path);
            }
        };
        $upload = fn ($bytes) => $this->post('/api/v1/projects/'.$project['id'].'/attachments', ['requestId' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('archive.zip', $bytes)], ['Accept' => 'application/json']);
        $valid = $make('notes/readme.txt', 'plain synthetic data');
        $id = $upload($valid)->assertOk()->json('data.id');
        $response = $this->get('/api/v1/projects/'.$project['id'].'/attachments/'.$id.'/content')->assertOk()->assertHeader('Content-Type', 'application/zip')->assertStreamedContent($valid);
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
        foreach ([$make('../outside.txt', 'data'), $make('/absolute.txt', 'data'), $make('link', '../secret', link: true), $make('encrypted.txt', 'data', encrypted: true), $make('bomb.txt', str_repeat('a', 1024 * 1024))] as $invalid) {
            $upload($invalid)->assertStatus(400);
        }
        config(['attachments.zip_max_expanded_bytes' => 1]);
        $upload($valid)->assertStatus(400);
        config(['attachments.zip_max_expanded_bytes' => 104857600, 'attachments.zip_max_entries' => 0]);
        $upload($valid)->assertStatus(400);
        $this->assertSame(1, $this->attachmentRows()->count());
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

            /** 注入私有儲存失敗
             * @param  string  $disk  測試disk
             * @param  string  $key  測試key
             * @param  string  $path  圖片暫存路徑
             * @return void
             */
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

            /** 注入清理失敗與重試
             * @param  string  $disk  測試disk
             * @param  string  $key  測試key
             * @return bool 清理結果
             */
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

            /** 注入完成保存失敗
             * @param  Attachment  $attachment  隔離附件
             * @param  array  $attributes  保存欄位
             * @return Attachment 保存結果
             */
            public function save(Attachment $attachment, array $attributes): Attachment
            {
                if ($this->failFinalize && ($attributes['status'] ?? '') === 'pending') {
                    DomainError::fail('CONFLICT', 'synthetic finalize failure');
                }

                return parent::save($attachment, $attributes);
            }

            /** 注入更新卡片後失敗
             * @param  Issue  $issue  隔離卡片
             * @param  array  $event  合成事件
             * @return void
             */
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
