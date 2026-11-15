<?php

namespace App\Data;

use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectTag;

/** 僅在同一交易內使用的已授權寫入資源, 不保存跨請求狀態 */
final readonly class TagWriteScope
{
    /** 綁定同一交易內授權的專案與目標資源
     *
     * @param  Project  $project  已授權專案
     * @param  ?ProjectTag  $tag  修改／刪除定義
     * @param  ?Issue  $issue  差量套標卡片
     */
    public function __construct(public Project $project, public ?ProjectTag $tag = null, public ?Issue $issue = null) {}
}
