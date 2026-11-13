<?php

namespace App\Http\Requests;

use App\Data\IssueQueryData;
use Illuminate\Validation\Rule;

/** 任務篩選allowlist，未知欄位不進查詢服務。 */
class IssueQueryRequest extends PageQueryRequest
{
    /**
     * 僅接受此查詢需要的欄位與有界值。
     *
     * @return array<string,mixed> 驗證規則
     */
    public function rules(): array
    {
        return [...parent::rules(),
            'identifier' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'array', 'max:30'], 'state.*' => ['string', 'max:40'],
            'priority' => ['sometimes', 'nullable', Rule::in(['urgent', 'high', 'normal', 'low'])],
            'type' => ['sometimes', 'nullable', Rule::in(['epic', 'task', 'bug'])],
            'assignee' => ['sometimes', 'nullable', 'string', 'max:100'], 'epicId' => ['sometimes', 'nullable', 'string', 'max:100'],
            'condition' => ['sometimes', 'nullable', Rule::in(['overdue', 'unassigned'])],
            'sort' => ['sometimes', Rule::in(['updated', 'created', 'priority', 'due'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'scope' => ['sometimes', Rule::in(['all', 'mine'])],
        ];
    }

    /**
     * 將allowlist欄位轉成與HTTP無關的查詢條件。
     *
     * @return IssueQueryData 已驗證的卡片查詢條件
     */
    public function toDto(): IssueQueryData
    {
        $d = $this->validated();

        return new IssueQueryData($this->pagination(), $d['state'] ?? [], $d['priority'] ?? '', $d['type'] ?? '', $d['assignee'] ?? '', $d['epicId'] ?? '', $d['condition'] ?? '', $d['sort'] ?? 'updated', $d['scope'] ?? 'all', $d['identifier'] ?? '', $d['direction'] ?? '');
    }
}
