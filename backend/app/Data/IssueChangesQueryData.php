<?php

namespace App\Data;

/** 增量摘要的已驗證輸入，不包含可信身分。 */
readonly class IssueChangesQueryData
{
    /**
     * 建立有界篩選、水位與接續識別的不可變資料。
     *
     * @param  IssueQueryData  $filters  共用SQL篩選與每頁上限
     * @param  string  $cursor  不透明接續識別，空字串為首批
     * @param  ?string  $updatedSince  包含邊界的UTC ISO時間，null為全量起點
     */
    public function __construct(public IssueQueryData $filters, public string $cursor = '', public ?string $updatedSince = null) {}
}
