<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\TodoAttachment;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Repositories\RecordStore;
use App\Services\AttachmentCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** TT-294：紙條私有圖片只給經手者, Project讀權或工作區管理角色不能借用；暫存只屬上傳者 */
class TodoAttachmentTest extends TestCase
{
    use RefreshDatabase;

    /** @var array 建立人 */
    private array $owner;

    /** @var array 承辦人 */
    private array $alice;

    /** @var array 有專案讀權但未經手的管理者 */
    private array $manager;

    /** 1x1 PNG */
    private string $png;

    /** 建立工作區、承辦人與未經手的管理者
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('attachments');
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII=');
        $this->owner = $this->postJson('/api/auth/register', ['name' => 'Owner', 'email' => 'todo-image-owner@example.test', 'password' => 'test-local-password', 'workspaceName' => '紙條圖片'])->assertOk()->json('data.user');
        $tenant = (new RecordStore)->forWorkspace(session('workspace_id'));
        foreach (['alice' => 'user', 'manager' => 'manager'] as $name => $role) {
            $user = User::create(['id' => (string) Str::uuid(), 'name' => $name, 'email' => $name.'-todo-image@example.test', 'password' => Hash::make('test-local-password')]);
            $tenant->put('members', $user->id, ['id' => $user->id, 'name' => $name, 'email' => $user->email, 'role' => $role]);
            $this->{$name} = ['id' => $user->id, 'email' => $user->email];
        }
    }

    /** 切換登入身分
     * @param  array  $member  測試帳號
     * @return void
     */
    private function login(array $member): void
    {
        $this->postJson('/api/auth/logout');
        $this->postJson('/api/auth/login', ['email' => $member['email'], 'password' => 'test-local-password'])->assertOk();
    }

    /** 上傳紙條圖片
     * @param  string|null  $requestId  上傳識別碼
     * @param  string|null  $bytes  內容
     * @param  string  $name  檔名
     * @return TestResponse 回應
     */
    private function upload(?string $requestId = null, ?string $bytes = null, string $name = 'shot.png')
    {
        return $this->post('/api/v1/todo-attachments', ['requestId' => $requestId ?? (string) Str::uuid(), 'file' => UploadedFile::fake()->createWithContent($name, $bytes ?? $this->png)], ['Accept' => 'application/json']);
    }

    /** RPC
     * @param  string  $op  操作
     * @param  array  $input  輸入
     * @return TestResponse 回應
     */
    private function rpc(string $op, array $input)
    {
        return $this->postJson('/api/rpc/'.$op, ['requestId' => (string) Str::uuid(), ...$input]);
    }

    /** 經手者可讀、未經手的管理者不可讀；暫存只屬上傳者；冪等與型別限制
     * @return void
     */
    public function test_participants_only_and_pending_belongs_to_uploader(): void
    {
        $request = (string) Str::uuid();
        $image = $this->upload($request)->assertOk()->assertJsonPath('data.status', 'pending')->json('data');
        $this->assertSame($image['id'], $this->upload($request)->assertOk()->json('data.id'), '同requestId重送回同一筆');
        $this->upload($request, $this->png, 'renamed.png')->assertStatus(409);
        $this->upload(null, '%PDF-1.4 fake', 'doc.pdf')->assertStatus(400);
        $content = '/api/v1/todo-attachments/'.$image['id'].'/content';
        $this->get($content)->assertOk();
        $this->login($this->alice);
        $this->get($content)->assertStatus(404);

        $this->login($this->owner);
        $todo = $this->rpc('createTodo', ['content' => "看圖\n\n![shot](attachment:{$image['id']})", 'assigneeId' => $this->alice['id']])->assertOk()->json('data');
        $this->assertSame('active', TodoAttachment::where('public_id', $image['id'])->value('status'));
        $this->login($this->alice);
        $this->get($content)->assertOk();
        $this->login($this->manager);
        $this->get($content)->assertStatus(404);
        $this->rpc('commentTodo', ['id' => $todo['id'], 'content' => '偷看', 'expectedRevision' => 1])->assertStatus(404);

        WorkspaceMember::where('user_id', $this->alice['id'])->update(['disabled_at' => now()]);
        $this->login($this->owner);
        $this->get($content)->assertOk();
    }

    /** 附言只能綁自己的暫存；經手者附言帶圖後其他經手者可讀
     * @return void
     */
    public function test_reply_binds_only_own_pending_images(): void
    {
        $ownerDraft = $this->upload()->assertOk()->json('data');
        $todo = $this->rpc('createTodo', ['content' => '請看', 'assigneeId' => $this->alice['id']])->assertOk()->json('data');
        $this->login($this->alice);
        $this->rpc('commentTodo', ['id' => $todo['id'], 'content' => "![x](attachment:{$ownerDraft['id']})", 'expectedRevision' => 1])->assertStatus(404);
        $mine = $this->upload()->assertOk()->json('data');
        $this->rpc('commentTodo', ['id' => $todo['id'], 'content' => "附圖 ![x](attachment:{$mine['id']})", 'expectedRevision' => 1])->assertOk();
        $this->login($this->owner);
        $this->get('/api/v1/todo-attachments/'.$mine['id'].'/content')->assertOk();
        $this->assertSame('pending', TodoAttachment::where('public_id', $ownerDraft['id'])->value('status'), '失敗的附言不綁定');
    }

    /** 修改內容綁定新圖；Issue附件端點讀不到紙條圖片；轉卡複製圖片且不能借用別張紙條的圖
     * @return void
     */
    public function test_update_binds_and_issue_endpoints_and_convert_do_not_leak(): void
    {
        $todo = $this->rpc('createTodo', ['content' => '草稿'])->assertOk()->json('data');
        $image = $this->upload()->assertOk()->json('data');
        $this->rpc('updateTodo', ['id' => $todo['id'], 'content' => "補圖 ![x](attachment:{$image['id']})", 'expectedRevision' => 1])->assertOk();
        $this->assertSame('active', TodoAttachment::where('public_id', $image['id'])->value('status'));
        $project = $this->rpc('createProject', ['name' => 'Leak', 'code' => 'LEAK'])->assertOk()->json('data');
        $this->get('/api/v1/projects/'.$project['id'].'/attachments/'.$image['id'].'/content')->assertStatus(404);
        // 引用別張紙條的圖片不能借轉卡帶進 Issue。
        $other = $this->rpc('createTodo', ['content' => '另一張'])->assertOk()->json('data');
        $foreign = $this->upload()->assertOk()->json('data');
        $this->rpc('updateTodo', ['id' => $other['id'], 'content' => "![y](attachment:{$foreign['id']})", 'expectedRevision' => 1])->assertOk();
        $this->rpc('convertTodo', ['id' => $todo['id'], 'expectedRevision' => 2, 'issue' => ['projectId' => $project['id'], 'description' => "![y](attachment:{$foreign['id']})"]])->assertStatus(404);

        // 含圖轉卡：複製成 Issue 附件並改寫引用, 紙條原檔維持私有。
        $this->rpc('convertTodo', ['id' => $todo['id'], 'expectedRevision' => 2, 'issue' => ['projectId' => $project['id']]])->assertOk();
        $description = Issue::whereHas('project', fn ($q) => $q->where('public_id', $project['id']))->latest('id')->value('description');
        preg_match('/attachment:([0-9a-f-]{36})/', $description, $match);
        $this->assertNotEmpty($match);
        $this->assertNotSame($image['id'], $match[1], 'Issue 引用新複本, 不是紙條原檔');
        $this->get('/api/v1/projects/'.$project['id'].'/attachments/'.$match[1].'/content')->assertOk();
        $this->assertSame('active', TodoAttachment::where('public_id', $image['id'])->value('status'));
        $this->login($this->manager);
        $this->get('/api/v1/projects/'.$project['id'].'/attachments/'.$match[1].'/content')->assertOk();
        $this->get('/api/v1/todo-attachments/'.$image['id'].'/content')->assertStatus(404);
    }

    /** 逾期暫存由既有清理流程清除, 已綁定不受影響
     * @return void
     */
    public function test_expired_pending_is_purged(): void
    {
        $stale = $this->upload()->assertOk()->json('data');
        $kept = $this->upload()->assertOk()->json('data');
        $this->rpc('createTodo', ['content' => "![x](attachment:{$kept['id']})"])->assertOk();
        TodoAttachment::where('public_id', $stale['id'])->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(['purged' => 1, 'failed' => 0], app(AttachmentCleanupService::class)->run());
        $this->assertSame('purged', TodoAttachment::where('public_id', $stale['id'])->value('status'));
        $this->assertSame('active', TodoAttachment::where('public_id', $kept['id'])->value('status'));
        $this->get('/api/v1/todo-attachments/'.$stale['id'].'/content')->assertStatus(404);
    }
}
