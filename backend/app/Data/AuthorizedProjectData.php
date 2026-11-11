<?php

namespace App\Data;

use App\Models\Project;

/** 當次查詢快照的Project與有效permission，不作跨請求快取。 */
readonly class AuthorizedProjectData
{
    /**
     * 保存同一資格快照的Project及權限。
     *
     * @param  Project  $project  已限定租戶的Project
     * @param  array<int,string>  $permissions  當次有效permission
     */
    public function __construct(public Project $project, public array $permissions) {}
}
