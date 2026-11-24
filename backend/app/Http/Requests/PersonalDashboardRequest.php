<?php

namespace App\Http\Requests;

/** 限制首頁摘要種類、頁數與每頁資料量, 不接受操作者或工作區條件。 */
class PersonalDashboardRequest extends PageQueryRequest
{
    /**
     * 限制深分頁成本, 各摘要最多每頁20筆。
     *
     * @return array<string,mixed> 首頁查詢規則
     */
    public function rules(): array
    {
        return ['kind' => ['required', 'in:mine,due,starting,recent'], 'page' => ['sometimes', 'integer', 'min:1', 'max:501'], 'perPage' => ['sometimes', 'integer', 'min:1', 'max:20']];
    }
}
