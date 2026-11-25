<?php

namespace App\Http\Requests;

use App\Data\PageQueryData;
use App\Data\ScheduleQueryData;

/** 時程查詢只接受日期區間、承辦者與分頁, 不接受工作區或操作者條件。 */
class ScheduleQueryRequest extends PageQueryRequest
{
    /**
     * 每頁最多1000筆, 視圖一次讀完整期間並提示超限。
     *
     * @return array<string,mixed> 時程查詢規則
     */
    public function rules(): array
    {
        return ['from' => ['required', 'string', 'size:10'], 'to' => ['required', 'string', 'size:10'], 'assignee' => ['sometimes', 'nullable', 'string', 'max:100'],
            'includeClosed' => ['sometimes', 'boolean'], 'page' => ['sometimes', 'integer', 'min:1', 'max:1000'], 'perPage' => ['sometimes', 'integer', 'min:1', 'max:1000']];
    }

    /**
     * 轉成已驗證跨度的時程條件。
     *
     * @return ScheduleQueryData 時程條件
     */
    public function schedule(): ScheduleQueryData
    {
        $data = $this->validated();

        return ScheduleQueryData::range($data['from'], $data['to'], (string) ($data['assignee'] ?? ''), new PageQueryData((int) ($data['page'] ?? 1), (int) ($data['perPage'] ?? 1000)), (bool) ($data['includeClosed'] ?? false));
    }
}
