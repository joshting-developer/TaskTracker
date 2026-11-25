<?php

namespace App\Http\Requests;

/** 限制首頁摘要種類、頁數與每頁資料量, 不接受操作者或工作區條件。 */
class PersonalDashboardRequest extends PageQueryRequest
{
    /** 任務小工具可保存與查詢的條件, 不接受操作者或工作區。 */
    public static function filterRules(string $prefix = ''): array
    {
        return [
            $prefix.'projectId' => ['sometimes', 'string', 'uuid'],
            $prefix.'state' => ['sometimes', 'string', 'max:40'],
            $prefix.'priority' => ['sometimes', 'in:urgent,high,normal,low'],
            $prefix.'assignee' => ['sometimes', 'string', 'max:100'],
            $prefix.'tagId' => ['sometimes', 'uuid'],
            $prefix.'dueFrom' => ['sometimes', 'date_format:Y-m-d'],
            $prefix.'dueTo' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    /**
     * 限制深分頁成本, 各摘要最多每頁20筆。
     *
     * @return array<string,mixed> 首頁查詢規則
     */
    public function rules(): array
    {
        return [...self::filterRules(), 'kind' => ['required', 'in:mine,due,starting,recent,tasks'], 'page' => ['sometimes', 'integer', 'min:1', 'max:501'], 'perPage' => ['sometimes', 'integer', 'min:1', 'max:20']];
    }
}
