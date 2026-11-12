<?php

namespace App\Http\Resources\Queries;

use Illuminate\Http\Request;

/** TT-173：增量摘要＝列表欄位＋外部識別；不改Web列表DTO。 */
class IssueChangeResource extends IssueListResource
{
    /**
     * 列表欄位加上已預載的外部識別，不另查DB。
     *
     * @param  Request  $request  HTTP請求
     * @return array<string,mixed> 公開增量項目
     */
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'externalRefs' => $this->resource->getRelation('externalRefs')
            ->map(fn ($ref) => ['source' => $ref->source, 'externalId' => $ref->external_id])->values()->all()];
    }
}
