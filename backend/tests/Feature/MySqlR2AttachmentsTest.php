<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Services\AttachmentCleanupService;
use Aws\Middleware;
use GuzzleHttp\Client;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\MySqlTestCase;

/** 必須明確開啟且只用隔離Bucket；finally只清本次隨機物件 */
class MySqlR2AttachmentsTest extends MySqlTestCase
{
    use DatabaseTransactions;

    /**
     * 真Laravel／MySQL／R2驗上傳、Session與PAT授權串流、跨租戶拒絕、刪除與實際清理
     *
     * @return void
     */
    public function test_real_r2_private_attachment_lifecycle(): void
    {
        if (getenv('TASKTRACKER_R2_TEST') !== '1') {
            $this->markTestSkipped('Explicit isolated R2 test required');
        }
        $settings = config('filesystems.disks.r2-attachments');
        $this->assertSame('task-tracker-local', $settings['bucket']);
        $this->assertTrue($settings['http']['verify']);
        config(['attachments.disk' => 'r2-attachments']);
        $register = fn () => $this->postJson('/api/auth/register', ['name' => 'R2 test', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-r2-lifecycle', 'workspaceName' => 'R2 test'])->assertOk()->json('data');
        $owner = $register();
        $project = $this->postJson('/api/rpc/createProject', ['name' => 'R2 project', 'code' => 'R2TEST', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $issue = $this->postJson('/api/rpc/createIssue', ['projectId' => $project['id'], 'title' => 'R2 image', 'requestId' => (string) Str::uuid()])->assertOk()->json('data');
        $base = '/api/v1/projects/'.$project['id'].'/attachments';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        $commands = [];
        Storage::disk('r2-attachments')->getClient()->getHandlerList()->appendInit(Middleware::tap(function ($command) use (&$commands) {
            $name = $command->getName();
            $commands[$name] = ($commands[$name] ?? 0) + 1;
        }), 'tt291-command-count');
        $key = null;
        try {
            $request = (string) Str::uuid();
            $upload = fn () => $this->post($base, ['requestId' => $request, 'file' => UploadedFile::fake()->createWithContent('r2.png', $png)], ['Accept' => 'application/json'])->assertOk()->json('data');
            $attachment = $upload();
            $row = Attachment::where('public_id', $attachment['id'])->sole();
            $key = $row->storage_key;
            $this->assertSame('r2-attachments', $row->storage_disk);
            $this->assertTrue(Storage::disk('r2-attachments')->exists($key));
            $anonymous = new Client(['http_errors' => false, 'allow_redirects' => false, 'verify' => true, 'connect_timeout' => 3, 'timeout' => 15]);
            $unsigned = Storage::disk('r2-attachments')->getClient()->getObjectUrl($settings['bucket'], $key);
            $unsignedResponse = $anonymous->get($unsigned);
            $this->assertContains($unsignedResponse->getStatusCode(), [400, 401, 403], 'Unsigned S3 object reads are rejected');
            $this->assertNotSame($png, (string) $unsignedResponse->getBody());
            fwrite(STDOUT, 'R2 unsigned response: '.$unsignedResponse->getStatusCode().PHP_EOL);
            $this->assertSame($attachment['id'], $upload()['id']);
            $content = $base.'/'.$attachment['id'].'/content';
            $this->get($content)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertStreamedContent($png);
            $this->postJson($base.'/'.$attachment['id'].'/bind', ['requestId' => (string) Str::uuid(), 'expectedRevision' => 1, 'issueId' => $issue['id'], 'issueRevision' => 1])->assertOk();
            $token = $this->postJson('/api/v1/personal-tokens', ['name' => 'R2 read', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
            $this->get('/api/public/v1/projects/'.$project['id'].'/attachments/'.$attachment['id'].'/content', ['Authorization' => 'Bearer '.$token])->assertOk()->assertStreamedContent($png);
            Auth::logout();
            $register();
            $this->get($content)->assertStatus(404);
            $foreignToken = $this->postJson('/api/v1/personal-tokens', ['name' => 'foreign', 'abilities' => ['read'], 'requestId' => (string) Str::uuid()])->assertOk()->json('data.plainTextToken');
            $this->getJson('/api/public/v1/projects/'.$project['id'].'/attachments/'.$attachment['id'], ['Authorization' => 'Bearer '.$foreignToken])->assertStatus(404);
            $this->postJson('/api/auth/login', ['email' => $owner['user']['email'], 'password' => 'isolated-r2-lifecycle'])->assertOk();
            $this->deleteJson($base.'/'.$attachment['id'], ['requestId' => (string) Str::uuid(), 'expectedRevision' => 2, 'issueId' => $issue['id'], 'issueRevision' => 2])->assertOk();
            $this->get($content)->assertStatus(404);
            $row->refresh();
            $this->assertSame('deleted', $row->status);
            $row->update(['expires_at' => now()->subMinute()]);
            $cleanup = $this->app->make(AttachmentCleanupService::class)->run();
            $this->assertSame(0, $cleanup['failed']);
            $this->assertSame('purged', $row->fresh()->status);
            $this->assertFalse(Storage::disk('r2-attachments')->exists($key));
            $this->assertSame(1, $commands['PutObject'] ?? 0, 'Successful replay does not write an object twice');
        } finally {
            if ($key !== null) {
                $this->assertTrue(Storage::disk('r2-attachments')->delete($key));
            }
        }
        fwrite(STDOUT, 'R2 logical commands including final cleanup: '.json_encode($commands).'; payload bytes='.strlen($png).PHP_EOL);
    }
}
