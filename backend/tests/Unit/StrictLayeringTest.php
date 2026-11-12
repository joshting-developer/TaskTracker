<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\LayeringInventory;

/** 防止HTTP方法服務注入、跳層、Service直接Model／SQL存取或定位器重新引入。 */
class StrictLayeringTest extends TestCase
{
    /**
     * 以別名與動態scope範例確認AST門檻會拒絕真違規而允許資料型別及Request輸入。
     *
     * @return void
     */
    public function test_architecture_gate_rejects_injection_and_data_access_regressions(): void
    {
        $scan = new LayeringInventory;
        $bad = '<?php use App\Models\User as Account; use App\Services\IssueReadService as Reader; use Illuminate\Support\Facades\DB as Database; class Example { public function index(Reader $service) { Account::where("id",1)->first(); Database::table("users")->get(); $model->save(); return app(Reader::class); } }';
        $issues = $scan->inspect($bad, true);
        foreach (['action-service-injection', 'model-static-io', 'direct-sql', 'model-instance-io', 'service-locator'] as $rule) {
            $this->assertStringContainsString($rule, implode('\n', $issues));
        }
        $good = '<?php use App\Repositories\AccountRepository; use App\Http\Requests\IssueQueryRequest; use App\Models\User; class Example { public function __construct(private AccountRepository $accounts) {} public function find(User $user): string { return $user->name; } }';
        $this->assertSame([], $scan->inspect($good, false));
        $this->assertStringContainsString('controller-repository', implode('\n', $scan->inspect($good, true)));
    }

    /**
     * 完整掃描現有HTTP Controller與正常Services，資料升級工具已移至Database明確邊界。
     *
     * @return void
     */
    public function test_all_controllers_and_application_services_follow_strict_layers(): void
    {
        $scan = new LayeringInventory;
        $violations = [];
        foreach (['Http/Controllers' => true, 'Services' => false] as $folder => $controller) {
            $root = dirname(__DIR__, 2).'/app/'.$folder;
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                foreach ($scan->inspect(file_get_contents($file->getPathname()), $controller) as $issue) {
                    $violations[] = substr($file->getPathname(), strlen($root) + 1).':'.$issue;
                }
            }
        }
        $this->assertSame([],$violations);
    }
}
