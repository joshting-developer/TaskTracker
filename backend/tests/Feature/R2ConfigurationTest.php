<?php

namespace Tests\Feature;

use App\Services\AttachmentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** 不連R2, 驗設定錯誤在metadata保留前拒絕且不洩漏secret */
class R2ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 缺設定、非HTTPS、URL注入或關閉TLS皆回明確錯誤且不占用配額
     *
     * @return void
     */
    public function test_invalid_r2_configuration_does_not_reserve_uploads(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Config', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-r2-config', 'workspaceName' => 'Config'])->assertOk();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Config', 'code' => 'CFG', 'requestId' => (string) Str::uuid()])->assertOk()->json('data.id');
        $valid = ['driver' => 's3', 'key' => 'synthetic-key', 'secret' => 'synthetic-secret', 'region' => 'auto', 'bucket' => 'task-tracker-local', 'endpoint' => 'https://'.str_repeat('a', 32).'.r2.cloudflarestorage.com', 'visibility' => 'private', 'serve' => false, 'http' => ['verify' => true]];
        config(['attachments.disk' => 'r2-attachments']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        $bad = [];
        foreach (['key', 'secret', 'region', 'bucket', 'endpoint'] as $field) {
            $bad[] = [...$valid, $field => ''];
        }
        foreach (['http://'.str_repeat('a', 32).'.r2.cloudflarestorage.com', $valid['endpoint'].'/bucket', $valid['endpoint'].'?secret=bad', 'https://user@'.str_repeat('a', 32).'.r2.cloudflarestorage.com', 'https://example.test'] as $endpoint) {
            $bad[] = [...$valid, 'endpoint' => $endpoint];
        }
        $bad[] = [...$valid, 'http' => ['verify' => false]];
        $bad[] = [...$valid, 'visibility' => 'public'];
        $bad[] = [...$valid, 'driver' => 'local'];
        foreach ($bad as $config) {
            config(['filesystems.disks.r2-attachments' => $config]);
            $response = $this->post('/api/v1/projects/'.$project.'/attachments', ['requestId' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('test.png', $png)], ['Accept' => 'application/json'])->assertStatus(502)->assertJsonPath('error.code', 'STORAGE_ERROR');
            $this->assertStringNotContainsString('synthetic-secret', $response->getContent());
            $this->assertSame(0, DB::table('attachments')->count());
        }
        config(['filesystems.disks.r2-attachments' => $valid]);
        $this->app->make(AttachmentStorage::class)->assertConfigured('r2-attachments');
    }

    /**
     * 預設改R2不會把舊local metadata當R2 key讀取, 也不自動搬物件
     *
     * @return void
     */
    public function test_existing_local_attachment_uses_its_recorded_disk(): void
    {
        Storage::fake('attachments');
        config(['attachments.disk' => 'attachments']);
        $this->postJson('/api/auth/register', ['name' => 'Local', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-r2-config', 'workspaceName' => 'Local'])->assertOk();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'Local', 'code' => 'LOC', 'requestId' => (string) Str::uuid()])->assertOk()->json('data.id');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        $attachment = $this->post('/api/v1/projects/'.$project.'/attachments', ['requestId' => (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent('local.png', $png)], ['Accept' => 'application/json'])->assertOk()->json('data');
        config(['attachments.disk' => 'r2-attachments', 'filesystems.disks.r2-attachments.secret' => '']);
        $this->get('/api/v1/projects/'.$project.'/attachments/'.$attachment['id'].'/content')->assertOk()->assertStreamedContent($png);
        $this->assertSame('attachments', DB::table('attachments')->where('public_id', $attachment['id'])->value('storage_disk'));
    }
}
