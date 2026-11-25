<?php

namespace App\Http\Requests;

use App\Data\PageQueryData;
use App\Data\ScheduleQueryData;

/** TT-446：未排程清單只接受承辦者、已關閉切換與分頁, 不接受日期區間、工作區或操作者條件。 */
class ScheduleUnscheduledRequest extends PageQueryRequest
{
    /**
     * 對話框每頁最多50筆。
     *
     * @return array<string,mixed> 未排程清單規則
     */
    public function rules(): array
    {
        return ['assignee' => ['sometimes', 'nullable', 'string', 'max:100'], 'includeClosed' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000'], 'perPage' => ['sometimes', 'integer', 'min:1', 'max:50']];
    }

    /**
     * 轉成與時程視圖相同的條件；未排程不使用日期區間, 起迄留空。
     *
     * @return ScheduleQueryData 承辦者、已關閉與分頁條件
     */
    public function filters(): ScheduleQueryData
    {
        $data = $this->validated();

        return new ScheduleQueryData('', '', (string) ($data['assignee'] ?? ''), new PageQueryData((int) ($data['page'] ?? 1), (int) ($data['perPage'] ?? 20)), (bool) ($data['includeClosed'] ?? false));
    }
}
