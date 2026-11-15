<?php

namespace App\Http\Requests;

/** 工作區成員列表只增加明確啟用篩選, 沿用有界分頁 */
class WorkspaceMembersQueryRequest extends PageQueryRequest
{
    /** 限定成員狀態查詢
     * @return array<string,mixed> 驗證規則
     */
    public function rules(): array
    {
        return [...parent::rules(), 'status' => ['sometimes', 'in:active,disabled']];
    }

    /** 回傳已驗證的資格狀態
     * @return string active或disabled
     */
    public function status(): string
    {
        return $this->validated()['status'] ?? 'active';
    }
}
