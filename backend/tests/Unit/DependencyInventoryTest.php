<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\DependencyInventory;

/** 驗證純token盤點不計入註解／字串／同名方法，且解析alias與constructor循環。 */
class DependencyInventoryTest extends TestCase
{
    /** @return void */
    public function test_lexical_sites_aliases_and_cycle_detection(): void
    {
        $scanner = new DependencyInventory;
        $a = $scanner->file('app/Services/A.php', <<<'CODE'
<?php
namespace App\Services;
use App\Repositories\Repo as R;
class A {
    public function __construct(private R $repo, array $options = []) {}
    public function work() {
        // new Fake(); app(Fake::class); resolve(Fake::class);
        $text = 'new Fake(); app(Fake::class)';
        $object->app(); A::resolve();
        new R(); app(R::class); \resolve(R::class); $app->make(R::class);
    }
}
CODE);
        $this->assertCount(4, $a['sites']);
        $this->assertSame(['new', 'app', 'resolve', 'container.make'], array_column($a['sites'], 'kind'));
        $this->assertSame(array_fill(0, 4, 'App\Repositories\Repo'), array_column($a['sites'], 'target'));
        $this->assertSame('App\Repositories\Repo', $a['constructorParameters'][0]['types'][0]);
        $b = $scanner->file('app/Repositories/Repo.php', '<?php namespace App\Repositories; use App\Services\A; class Repo { public function __construct(A $a) {} }');
        $graph = $scanner->graph([$a, $b]);
        $this->assertCount(1, $graph['cycles']);
        $this->assertSame($graph['cycles'][0][0], end($graph['cycles'][0]));
        $empty = $scanner->file('config/empty.php', '<?php return [];');
        $this->assertSame([], $empty['sites']);
    }
}
