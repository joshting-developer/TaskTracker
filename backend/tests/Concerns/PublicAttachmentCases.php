<?php

namespace Tests\Concerns;

use App\Domain\DomainError;
use App\Models\Attachment;
use App\Models\IntegrationAudit;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\AttachmentStorage;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Opis\JsonSchema\CompliantValidator;

/** 隔離HTTP／DB驗PAT附件, 私有物件IO使用fake disk */
trait PublicAttachmentCases
{
    /** 建立獨立帳號與read／write Token
     * @return array{array,array,string,string} 合成身分、專案、read與write PAT
     */
    private function attachmentTokenFixture(): array
    {
        Auth::forgetGuards();
        Storage::fake('attachments');
        $identity = $this->postJson('/api/auth/register', ['name' => 'Attachment client', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-attachment-client', 'workspaceName' => 'Attachments'])->assertOk()->json('data');
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Images', 'code' => 'ATT', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $token = fn ($ability) => $this->postJson('/api/v1/personal-tokens', ['name' => 'attachment-'.$ability, 'abilities' => [$ability], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');

        return [$identity, $project, $token('read'), $token('write')];
    }

    /** 使用真實multipart送出合成圖片
     * @param  string  $project  Project公開ID
     * @param  string  $token  純Bearer
     * @param  string  $request  requestId
     * @param  string  $name  合成圖片名稱
     * @param  int  $status  預期狀態
     * @return TestResponse 不含Session的新HTTP回應
     */
    private function publicAttachmentUpload(string $project, string $token, string $request, string $name = 'capture.png', int $status = 200): TestResponse
    {
        Auth::forgetGuards();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');

        return $this->post('/api/public/v1/projects/'.$project.'/attachments', ['requestId' => $request, 'file' => UploadedFile::fake()->createWithContent($name, $png)], ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token])->assertStatus($status)->assertCookieMissing(config('session.cookie'));
    }

    /** 比對唯一正式附件Schema
     * @param  mixed  $data  JSON資料
     * @param  string  $name  正式Schema名
     * @return void
     */
    private function attachmentSchema(mixed $data, string $name): void
    {
        $spec = json_decode(file_get_contents(resource_path('openapi.json')), true);
        $schema = json_decode(json_encode(['$schema' => 'https://json-schema.org/draft/2020-12/schema', '$ref' => '#/components/schemas/'.$name, 'components' => $spec['components']]));
        $this->assertTrue((new CompliantValidator)->validate(json_decode(json_encode($data)), $schema)->isValid(), 'Attachment response must match formal schema');
    }

    /** 同檔重送、metadata／binary、描述原子綁定與來源去重
     * @return void
     */
    public function test_public_upload_read_and_description_bind_share_the_existing_rules(): void
    {
        [$identity, $project, $read, $write] = $this->attachmentTokenFixture();
        $root = '/api/public/v1/projects/'.$project['id'];
        $request = (string) Str::uuid();
        $uploaded = $this->publicAttachmentUpload($project['id'], $write, $request)->json('data');
        $this->attachmentSchema($uploaded, 'Attachment');
        $this->assertSame('pending', $uploaded['status']);
        $count = IntegrationAudit::where('actor_user_id', $identity['user']['id'])->where('operation', 'public.v1.uploadAttachment')->count();
        $this->publicAttachmentUpload($project['id'], $write, $request)->assertExactJson(['ok' => true, 'data' => $uploaded]);
        $this->assertSame(1, $count);
        $this->assertSame($count, IntegrationAudit::where('actor_user_id', $identity['user']['id'])->where('operation', 'public.v1.uploadAttachment')->count());
        $this->assertSame(1, Attachment::where('uploaded_by', $identity['user']['id'])->count());
        $this->publicAttachmentUpload($project['id'], $write, $request, 'changed.png', 409)->assertJsonPath('error.code', 'REQUEST_CONFLICT');
        $headers = ['Authorization' => 'Bearer '.$read];
        $limits = $this->getJson($root.'/attachments/limits', $headers)->assertOk()->json('data');
        $this->attachmentSchema($limits, 'AttachmentLimits');
        $this->assertSame(10485760, $limits['maxBytes']);
        $this->getJson($root.'/attachments/'.$uploaded['id'], $headers)->assertOk()->assertExactJson(['ok' => true, 'data' => $uploaded]);
        $response = $this->get($root.'/attachments/'.$uploaded['id'].'/content?download=true', $headers)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("\x89PNG", $response->streamedContent());
        $this->assertFalse($response->headers->has('Location'));
        $input = ['title' => 'Public image', 'description' => '![capture]('.$uploaded['reference'].')', 'requestId' => (string) Str::uuid()];
        $issue = $this->postJson($root.'/issues', $input, ['Authorization' => 'Bearer '.$write])->assertOk()->json('data');
        $this->getJson($root.'/issues/'.$issue['id'].'/attachments', $headers)->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.perPage', 20)->assertJsonPath('data.items.0.status', 'active');
        $this->postJson($root.'/issues', $input, ['Authorization' => 'Bearer '.$write])->assertOk()->assertJsonPath('data.id', $issue['id']);
        $this->assertStringNotContainsString('storage_', json_encode($uploaded));
    }

    /** PAT留言也經相同附件規則, 不增加第二套寫入流程
     * @return void
     */
    public function test_public_comment_binds_images_with_scope_and_replay_checks(): void
    {
        [$identity, $project, $read, $write] = $this->attachmentTokenFixture();
        $root = '/api/public/v1/projects/'.$project['id'];
        $upload = $this->publicAttachmentUpload($project['id'], $write, (string) Str::uuid())->json('data');
        $issue = $this->postJson($root.'/issues', ['title' => 'Comment image', 'requestId' => (string) Str::uuid()], ['Authorization' => 'Bearer '.$write])->assertOk()->json('data');
        $path = $root.'/issues/'.$issue['id'].'/comments';
        $input = ['content' => '![capture]('.$upload['reference'].')', 'expectedRevision' => 1, 'requestId' => (string) Str::uuid()];
        $this->postJson($path, $input, ['Authorization' => 'Bearer '.$read])->assertForbidden();
        $this->assertSame('pending', Attachment::where('public_id', $upload['id'])->value('status'));
        $result = $this->postJson($path, $input, ['Authorization' => 'Bearer '.$write])->assertOk()->json('data');
        $this->postJson($path, $input, ['Authorization' => 'Bearer '.$write])->assertOk()->assertExactJson(['ok' => true, 'data' => $result]);
        $this->getJson($path, ['Authorization' => 'Bearer '.$read])->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.content', $input['content']);
        $this->assertSame('active', Attachment::where('public_id', $upload['id'])->value('status'));
        WorkspaceMember::where('user_id', $identity['user']['id'])->update(['role' => 'user']);
        $this->postJson($path, $input, ['Authorization' => 'Bearer '.$write])->assertNotFound();
    }

    /** PDF的PAT操作保留scope、下載標頭與正式Schema
     *
     * @return void
     */
    public function test_public_pdf_uses_download_only_contract_and_rechecks_access(): void
    {
        [$identity, $project, $read, $write] = $this->attachmentTokenFixture();
        $root = '/api/public/v1/projects/'.$project['id'];
        $pdf = "%PDF-1.7\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n";
        $payload = ['requestId' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('notes.pdf', $pdf)];
        Auth::forgetGuards();
        $this->post($root.'/attachments', $payload, ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$read])->assertForbidden();
        $row = $this->post($root.'/attachments', $payload, ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$write])->assertOk()->json('data');
        $this->attachmentSchema($row, 'Attachment');
        $response = $this->get($root.'/attachments/'.$row['id'].'/content', ['Authorization' => 'Bearer '.$read])->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($pdf);
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
        WorkspaceMember::where('user_id', $identity['user']['id'])->update(['role' => 'user']);
        $this->get($root.'/attachments/'.$row['id'].'/content', ['Authorization' => 'Bearer '.$read])->assertNotFound();
    }

    /** 雙版本綁定、原結果重播、read禁止寫入及資格撤銷
     * @return void
     */
    public function test_public_bind_is_revision_checked_and_reauthorized_before_replay(): void
    {
        [$identity, $project, $read, $write] = $this->attachmentTokenFixture();
        $root = '/api/public/v1/projects/'.$project['id'];
        $upload = $this->publicAttachmentUpload($project['id'], $write, (string) Str::uuid())->json('data');
        $issue = $this->postJson($root.'/issues', ['title' => 'Bind target', 'requestId' => (string) Str::uuid()], ['Authorization' => 'Bearer '.$write])->assertOk()->json('data');
        $path = $root.'/attachments/'.$upload['id'].'/bind';
        $input = ['requestId' => (string) Str::uuid(), 'expectedRevision' => 1, 'issueId' => $issue['id'], 'issueRevision' => 1];
        $this->postJson($path, $input, ['Authorization' => 'Bearer '.$read])->assertForbidden();
        $this->postJson($path, [...$input, 'issueRevision' => 99], ['Authorization' => 'Bearer '.$write])->assertStatus(409);
        $this->assertSame('pending', Attachment::where('public_id', $upload['id'])->value('status'));
        $bound = $this->postJson($path, $input, ['Authorization' => 'Bearer '.$write])->assertOk()->json('data');
        $this->attachmentSchema($bound, 'AttachmentMutationResult');
        $this->postJson($path, $input, ['Authorization' => 'Bearer '.$write])->assertOk()->assertExactJson(['ok' => true, 'data' => $bound]);
        $this->assertSame(1, IntegrationAudit::where('actor_user_id', $identity['user']['id'])->where('operation', 'public.v1.bindAttachment')->count());
        WorkspaceMember::where('user_id', $identity['user']['id'])->update(['role' => 'user']);
        $this->postJson($path, $input, ['Authorization' => 'Bearer '.$write])->assertNotFound();
        $this->getJson($root.'/attachments/'.$upload['id'], ['Authorization' => 'Bearer '.$read])->assertNotFound();
    }

    /** pending作者、租戶／Project、期限與Token失效皆拒絕
     * @return void
     */
    public function test_pending_scope_expiry_tokens_and_input_are_restricted(): void
    {
        [$identity, $project, $read, $write] = $this->attachmentTokenFixture();
        $root = '/api/public/v1/projects/'.$project['id'];
        $this->publicAttachmentUpload($project['id'], $read, (string) Str::uuid(), status: 403);
        $upload = $this->publicAttachmentUpload($project['id'], $write, (string) Str::uuid())->json('data');
        $row = Attachment::where('public_id', $upload['id'])->firstOrFail();
        $foreign = User::factory()->create(['id' => (string) Str::uuid()]);
        $row->update(['uploaded_by' => $foreign->id]);
        $this->getJson($root.'/attachments/'.$upload['id'], ['Authorization' => 'Bearer '.$read])->assertNotFound();
        $row->update(['uploaded_by' => $identity['user']['id'], 'expires_at' => now()->subMinute()]);
        $this->get($root.'/attachments/'.$upload['id'].'/content', ['Authorization' => 'Bearer '.$read])->assertNotFound();
        $row->update(['expires_at' => now()->addHour()]);
        $other = $this->postJson('/api/rpc/createProject', ['name' => 'Other', 'code' => 'OTH', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $this->getJson('/api/public/v1/projects/'.$other['id'].'/attachments/'.$upload['id'], ['Authorization' => 'Bearer '.$read])->assertNotFound();
        [, , , $otherToken] = $this->attachmentTokenFixture();
        $this->getJson($root.'/attachments/'.$upload['id'], ['Authorization' => 'Bearer '.$otherToken])->assertNotFound();
        $this->post($root.'/attachments', ['requestId' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('unsafe.svg', '<svg onload="alert(1)"/>')], ['Authorization' => 'Bearer '.$write, 'Accept' => 'application/json'])->assertStatus(400);
        PersonalAccessToken::whereKey(explode('|', $write, 2)[0])->update(['revoked_at' => now()]);
        $this->assertNotNull(PersonalAccessToken::findToken($write)->revoked_at);
        $this->publicAttachmentUpload($project['id'], $write, (string) Str::uuid(), status: 401);
        PersonalAccessToken::findOrFail(explode('|', $read, 2)[0])->update(['expires_at' => now()->subMinute()]);
        $this->getJson($root.'/attachments/'.$upload['id'], ['Authorization' => 'Bearer '.$read])->assertUnauthorized();
    }

    /** IO失敗與IO期間撤權不寫成功receipt, 同檔可重試且不重複配額
     * @return void
     */
    public function test_upload_failure_and_revocation_between_io_and_completion_are_safe(): void
    {
        [$identity, $project, , $write] = $this->attachmentTokenFixture();
        $fault = new class(app(FilesystemManager::class)) extends AttachmentStorage
        {
            public bool $fail = true;

            public ?string $revokeUser = null;

            /** 注入IO失敗或完成前撤權
             * @param  string  $disk  隔離fake disk
             * @param  string  $key  測試私有key
             * @param  string  $path  合成圖片暫存檔
             * @return void
             */
            public function put(string $disk, string $key, string $path): void
            {
                if ($this->fail) {
                    DomainError::fail('STORAGE_ERROR', 'Injected IO failure');
                }
                parent::put($disk, $key, $path);
                if ($this->revokeUser) {
                    WorkspaceMember::where('user_id', $this->revokeUser)->update(['role' => 'user']);
                }
            }
        };
        $this->app->instance(AttachmentStorage::class, $fault);
        $request = (string) Str::uuid();
        $this->publicAttachmentUpload($project['id'], $write, $request, status: 502);
        $this->assertSame(0, IntegrationAudit::where('actor_user_id', $identity['user']['id'])->where('operation', 'public.v1.uploadAttachment')->count());
        $this->assertSame('uploading', Attachment::where('uploaded_by', $identity['user']['id'])->value('status'));
        $fault->fail = false;
        $this->publicAttachmentUpload($project['id'], $write, $request);
        $this->assertSame(1, Attachment::where('uploaded_by', $identity['user']['id'])->count());
        $fault->revokeUser = $identity['user']['id'];
        $this->publicAttachmentUpload($project['id'], $write, (string) Str::uuid(), status: 404);
        $this->assertSame(1, IntegrationAudit::where('actor_user_id', $identity['user']['id'])->where('operation', 'public.v1.uploadAttachment')->count());
        $this->assertSame(1, Attachment::where('uploaded_by', $identity['user']['id'])->where('status', 'uploading')->count());
    }
}
