<?php

namespace Tests\Unit;

use App\Http\Resources\Queries\IssueListPageResource;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\TestCase;

/** 可到達頁數與真實總數分開, 不為保護OFFSET偽造total */
class IssueListPageTest extends TestCase
{
    /**
     * 超過界限、空集合及每頁大小都回傳一致的可達範圍
     *
     * @return void
     */
    public function test_accessible_pages_preserve_actual_total(): void
    {
        foreach ([20 => 501, 100 => 101, 7 => 1429] as $size => $max) {
            $page = IssueListPageResource::wrap(new LengthAwarePaginator([], 50000, $size, 1), []);
            $this->assertSame(50000, $page['total']);
            $this->assertSame((int) ceil(50000 / $size), $page['lastPage']);
            $this->assertSame($max, $page['accessibleLastPage']);
        }
        $this->assertSame(1, IssueListPageResource::wrap(new LengthAwarePaginator([], 0, 20, 1), [])['accessibleLastPage']);
    }
}
