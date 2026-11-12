<?php

namespace App\Http\Resources\Queries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 序列化已授權的管理成員DTO，不觸發資料查詢。 */
class ProjectMemberResource extends JsonResource
{
    /**
     * 輸出成員來源、角色與可調整能力，不回傳完整權限歷史。
     *
     * @param  Request  $request  HTTP請求
     * @return array<string,mixed> 管理成員摘要
     */
    public function toArray(Request $request): array
    {
        $member = $this->resource;

        return ['userId' => $member->userId, 'name' => $member->name, 'email' => $member->email,
            'source' => $member->source, 'roleId' => $member->roleId, 'canChange' => $member->canChange];
    }
}
