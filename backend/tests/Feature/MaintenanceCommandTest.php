<?php

namespace Tests\Feature;

use App\Services\AttachmentCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/** 只在隔離資料驗證維護觸發、互斥與失敗退出 */
class MaintenanceCommandTest extends TestCase
{
    use RefreshDatabase;

    /** 清理失敗退出非0, 不假裝整輪成功
     * @return void
     */
    public function test_cleanup_failure_is_visible_and_lock_is_released(): void
    {
        $cleanup = Mockery::mock(AttachmentCleanupService::class);
        $cleanup->shouldReceive('run')->twice()->andReturn(['purged' => 2, 'failed' => 1], ['purged' => 1, 'failed' => 0]);
        $this->app->instance(AttachmentCleanupService::class, $cleanup);
        $this->artisan('tasktracker:maintenance')->expectsOutputToContain('"state":"failed"')->assertFailed();
        $this->artisan('tasktracker:maintenance')->expectsOutputToContain('"state":"completed"')->assertSuccessful();
    }

    /** 已有工作時不再碰清理, 不強搶其他程序鎖
     * @return void
     */
    public function test_overlapping_run_does_not_touch_data(): void
    {
        $lock = Cache::lock('tasktracker:maintenance', 900);
        $this->assertTrue($lock->get());
        $cleanup = Mockery::mock(AttachmentCleanupService::class);
        $cleanup->shouldNotReceive('run');
        $this->app->instance(AttachmentCleanupService::class, $cleanup);
        try {
            $this->artisan('tasktracker:maintenance')->expectsOutputToContain('"state":"busy"')->assertFailed();
        } finally {
            $lock->release();
        }
    }

    /** 空隔離庫可執行每日prune, 只操作指定觀測Model
     * @return void
     */
    public function test_daily_prune_uses_existing_retention_contract(): void
    {
        $cleanup = Mockery::mock(AttachmentCleanupService::class);
        $cleanup->shouldReceive('run')->once()->andReturn(['purged' => 0, 'failed' => 0]);
        $this->app->instance(AttachmentCleanupService::class, $cleanup);
        $this->artisan('tasktracker:maintenance', ['--prune' => true])->expectsOutputToContain('"pruneExitCode":0')->assertSuccessful();
    }
}
