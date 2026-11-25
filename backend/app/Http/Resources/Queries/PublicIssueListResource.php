<?php

namespace App\Http\Resources\Queries;

use Illuminate\Http\Request;

/** TT-447：公開 PAT 列表／看板／增量摘要另回計畫日期, Web 列表維持 TT-368 的精簡投影。 */
class PublicIssueListResource extends IssueListResource
{
    /**
     * 列表欄位加上計畫開始／結束日, 未填為空字串。
     *
     * @param  Request  $request  HTTP請求
     * @return array<string,mixed> 公開列表項目
     */
    public function toArray(Request $request): array
    {
        $issue = $this->resource;

        return [...parent::toArray($request), 'startDate' => $issue->start_date ?? '', 'endDate' => $issue->end_date ?? ''];
    }
}
