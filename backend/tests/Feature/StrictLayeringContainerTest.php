<?php

namespace Tests\Feature;

use App\Http\Controllers\Integrations\PublicProjectsController;
use App\Models\User;
use App\Repositories\AccountRepository;
use App\Services\AccountService;
use App\Services\Integrations\IntegrationAudits;
use App\Services\Integrations\IssueChangeService;
use App\Services\Integrations\PersonalTokens;
use App\Services\IssueReadService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** 真Container與初始化範圍證據，不以純字串掃描代替依賴可解析。 */
class StrictLayeringContainerTest extends TestCase
{
    /**
     * 全部Controller可解析，建構不查SQL；查Project不會解析Issue／Changes服務。
     *
     * @return void
     */
    public function test_controllers_resolve_without_sql_and_project_does_not_resolve_other_domains(): void
    {
        $made = [];
        $this->app->resolving(function ($object) use (&$made) {
            $made[] = get_class($object);
        });
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->app->make(PublicProjectsController::class);
        $this->assertNotContains(IssueReadService::class, $made);
        $this->assertNotContains(IssueChangeService::class, $made);
        foreach (glob(app_path('Http/Controllers/*.php')) as $path) {
            $class = 'App\\Http\\Controllers\\'.basename($path, '.php');
            if ($class !== 'App\\Http\\Controllers\\Controller') {
                $this->assertInstanceOf($class, $this->app->make($class));
            }
        }
        foreach (['Queries', 'Integrations'] as $folder) {
            foreach (glob(app_path('Http/Controllers/'.$folder.'/*.php')) as $path) {
                $class = 'App\\Http\\Controllers\\'.$folder.'\\'.basename($path, '.php');
                $this->assertInstanceOf($class, $this->app->make($class));
            }
        }
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    /**
     * Repository可在Container替換，Service不自行new或繞過注入模型查詢。
     *
     * @return void
     */
    public function test_account_repository_is_replaceable_and_token_constructor_is_explicit(): void
    {
        $repository = new class extends AccountRepository
        {
            /**
             * 不查資料庫的替換實作，讓依賴替換行為可觀測。
             *
             * @param  ?string  $id  可信User ID
             * @return ?User 替換查無結果
             */
            public function find(?string $id): ?User
            {
                return null;
            }
        };
        $this->app->instance(AccountRepository::class, $repository);
        $this->assertNull($this->app->make(AccountService::class)->freshUser('isolated'));
        $this->assertInstanceOf(PersonalTokens::class, $this->app->make(PersonalTokens::class));
        $this->assertInstanceOf(IntegrationAudits::class, $this->app->make(IntegrationAudits::class));
    }
}
