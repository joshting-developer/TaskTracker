<?php

namespace App\Http\Resources\Queries;

use App\Database\DatabaseTimes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 專案摘要與按需預載的狀態，不回傳ACL／設定歷史。 */
class ProjectResource extends JsonResource
{
    /** 序列化已授權且預載的投影，不做任何查詢。 */
    public function toArray(Request $request): array
    {
        $p = $this->resource;
        $data = ['id' => $p->public_id, 'name' => $p->name, 'code' => $p->code,
            'description' => $p->description, 'color' => $p->color,
            'leadId' => $p->getRelation('lead')?->user_id ?? '',
            'leadName' => $p->getRelation('lead')?->getRelation('user')?->name ?? '', 'revision' => (int) $p->revision,
            'createdAt' => DatabaseTimes::public($p->created_at), 'updatedAt' => DatabaseTimes::public($p->updated_at)];
        if ($p->relationLoaded('statuses')) {
            $data['statuses'] = $p->getRelation('statuses')->sortBy('position')->map(fn ($s) => ['id' => $s->code, 'label' => $s->label, 'color' => $s->color])->values()->all();
        }

        return $data;
    }
}
