<?php

namespace App\Data;

/** 看板收錄的有界純值快照, null 規則表示純手動模式 */
final readonly class BoardSelectionData
{
    /** 保存規則與手動例外, 不包含 actor 或權限
     * @param  BoardRuleData|null  $autoInclude  自動收錄條件
     * @param  list<string>  $selectedIssueIds  明確加入項目, Epic 保留動態子卡片
     * @param  list<string>  $excludedIssueIds  優先排除項目
     */
    public function __construct(public ?BoardRuleData $autoInclude, public array $selectedIssueIds, public array $excludedIssueIds) {}
}
