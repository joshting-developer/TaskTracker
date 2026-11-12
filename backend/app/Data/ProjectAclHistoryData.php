<?php

namespace App\Data;

/** 當頁ACL事件及批次解析的目標名稱，不包含成員目錄。 */
readonly class ProjectAclHistoryData
{
    /**
     * 保存一筆可見事件與姓名參照。
     *
     * @param  string  $id  事件穩定識別碼
     * @param  array<string,mixed>  $event  原授權歷史快照
     * @param  string|null  $targetName  同Workspace目標名稱，已不存在則null
     */
    public function __construct(public string $id, public array $event, public ?string $targetName) {}
}
