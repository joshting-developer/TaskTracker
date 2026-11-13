<?php

/** 隔離MySQL雙程序驗唯一事件與寄送claim, SMTP完全替換為人造邊界 */
if (getenv('APP_ENV') !== 'testing' || getenv('TASKTRACKER_MYSQL_TEST') !== '1' || getenv('DB_DATABASE') !== 'tasktracker_test' || getenv('DB_USERNAME') !== 'tasktracker_test') {
    throw new RuntimeException('Isolated MySQL testing configuration required');
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== 'tasktracker_test') {
    throw new RuntimeException('Unexpected schema');
}

use App\Data\MailDeliveryData;
use App\Models\MailDelivery;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\MailConnectionService;
use App\Services\MailDeliveryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

config(['mail.default' => 'array']);
Queue::fake();
Mail::fake();
$counter = tempnam(sys_get_temp_dir(), 'tt-mail-counter-');
$result = $counter.'-results';
$user = User::create(['id' => (string) Str::uuid(), 'name' => 'Synthetic mail concurrency', 'email' => Str::uuid().'@example.test', 'password' => 'isolated-mail-password']);
$workspace = Workspace::create(['public_id' => (string) Str::uuid(), 'name' => 'Synthetic mail concurrency']);
$member = WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner', 'joined_at' => now()]);
$app->instance(MailConnectionService::class, new class(Mail::getFacadeRoot(), $counter) extends MailConnectionService
{
    /** 注入假的Mail manager及人造計數檔
     * @param  Factory  $mail  Mail fake
     * @param  string  $counter  人造計數檔
     */
    public function __construct(Factory $mail, private string $counter)
    {
        parent::__construct($mail);
    }

    /** 不連SMTP, 並檢查此時沒有DB交易鎖
     * @return void
     */
    public function connect(): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('SMTP boundary holds transaction');
        }
        file_put_contents($this->counter, "connect\n", FILE_APPEND | LOCK_EX);
        usleep(100000);
    }
});
$data = new MailDeliveryData($user->id, 'parallel-'.Str::uuid(), 'diagnostic', 'Synthetic mail', 'Synthetic content', now()->addHour());
$children = [];
try {
    DB::disconnect();
    for ($n = 0; $n < 2; $n++) {
        $pid = pcntl_fork();
        if ($pid < 0) {
            throw new RuntimeException('Unable to fork');
        }
        if ($pid === 0) {
            try {
                DB::purge();
                $service = $app->make(MailDeliveryService::class);
                $row = $service->enqueue($data);
                $service->deliver($row->id);
                file_put_contents($result, $row->id."\n", FILE_APPEND | LOCK_EX);
                exit(0);
            } catch (Throwable $error) {
                fwrite(STDERR, 'Synthetic mail child failed: '.get_class($error)."\n");
                exit(1);
            }
        }
        $children[] = $pid;
    }
    $failed = false;
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        $failed = $failed || ! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0;
    }
    if ($failed) {
        throw new RuntimeException('Synthetic mail child failed');
    }
    DB::purge();
    $ids = file($result, FILE_IGNORE_NEW_LINES);
    $row = MailDelivery::where('user_id', $user->id)->firstOrFail();
    if (count($ids) !== 2 || count(array_unique($ids)) !== 1 || MailDelivery::where('user_id', $user->id)->count() !== 1 || $row->attempts !== 1 || $row->status !== 'sent' || count(file($counter)) !== 1) {
        throw new RuntimeException('Duplicate synthetic delivery detected');
    }
    echo "PASS: two MySQL processes, one event, one send claim, no SMTP IO inside transaction\n";
} finally {
    DB::purge();
    MailDelivery::where('user_id', $user->id)->delete();
    WorkspaceMember::whereKey($member->id)->delete();
    Workspace::whereKey($workspace->id)->delete();
    User::whereKey($user->id)->delete();
    @unlink($counter);
    @unlink($result);
}
