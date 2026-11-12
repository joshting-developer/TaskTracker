<?php

namespace App\Http\Resources\Queries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** 序列化已授權的Workspace成員DTO，不做關聯查詢。 */
class WorkspaceMemberResource extends JsonResource
{
    /**
     * 只輸出角色調整需要的欄位、版本與可管理能力。
     *
     * @param  Request  $request  HTTP請求
     * @return array<string,mixed> 當頁成員摘要
     */
    public function toArray(Request $request): array
    {
        $member = $this->resource;

        return ['id' => $member->id, 'name' => $member->name, 'email' => $member->email, 'roleId' => $member->roleId,
            'revision' => $member->revision, 'canChange' => $member->canChange];
    }
}
