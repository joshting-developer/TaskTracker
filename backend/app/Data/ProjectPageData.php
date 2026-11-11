<?php

namespace App\Data;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 已授權專案分頁及同一讀取快照的能力資訊。 */
readonly class ProjectPageData
{
    /** 建立此用例的相依與唯讀資料，不從HTTP取得身分。 */
    public function __construct(public LengthAwarePaginator $page, public array $permissions) {}
}
