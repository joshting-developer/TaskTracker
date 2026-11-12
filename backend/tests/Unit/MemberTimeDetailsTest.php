<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyMemberTimeDetails as MemberTimeDetails;

/** 驗證同卡跨日與同日多卡的聚合口徑，不依賴資料庫。 */
class MemberTimeDetailsTest extends TestCase
{
    /**
     * 日期分組保留每日總量；卡片分組只加總該卡原始紀錄。
     *
     * @return void
     */
    public function test_dates_and_issue_groups_preserve_raw_entry_totals(): void
    {
        $issues = [
            'epic' => ['id' => 'epic', 'projectId' => 'p', 'identifier' => 'P-1', 'title' => '目標'],
            'child' => ['id' => 'child', 'projectId' => 'p', 'identifier' => 'P-2', 'title' => '工作'],
        ];
        $entries = [
            ['id' => 'a', 'issueId' => 'epic', 'projectId' => 'p', 'workDate' => '2026-11-05', 'minutes' => 30, 'note' => '規劃'],
            ['id' => 'b', 'issueId' => 'child', 'projectId' => 'p', 'workDate' => '2026-11-05', 'minutes' => 60, 'note' => '開發'],
            ['id' => 'c', 'issueId' => 'child', 'projectId' => 'p', 'workDate' => '2026-11-06', 'minutes' => 15, 'note' => '修正'],
        ];
        $service = new MemberTimeDetails;
        $days = $service->get(['detailGroup' => 'dates'], $entries, $issues, ['p' => ['name' => '專案']]);
        $this->assertSame(['2026-11-06', '2026-11-05', '2026-11-05'], array_column($days['rows'], 'date'));
        $this->assertSame([15, 90, 90], array_column($days['rows'], 'dayMinutes'));
        $this->assertSame(105, array_sum(array_column($days['rows'], 'minutes')));
        $cards = $service->get(['detailGroup' => 'issues', 'entryGroupKey' => 'child'], $entries, $issues, ['p' => ['name' => '專案']]);
        $this->assertSame([75, 30], array_column($cards['rows'], 'minutes'));
        $this->assertSame(['修正', '開發'], array_column($cards['expanded']['rows'], 'note'));
        $this->assertSame([15, 60], array_column($cards['expanded']['rows'], 'minutes'));
    }
}
