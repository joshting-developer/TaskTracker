<?php

namespace App\Http\Requests;

use App\Data\IssueOptionQueryData;
use Illuminate\Validation\Rule;

/** 只接受選項種類與排除ID，不接受任意SQL欄位。 */
class IssueOptionQueryRequest extends PageQueryRequest
{
    /**
     * 驗證有界條件與允許的候選種類。
     *
     * @return array<string,mixed> Laravel驗證規則
     */
    public function rules(): array
    {
        return [...parent::rules(), 'kind' => ['sometimes', Rule::in(['issue', 'epic', 'parent'])], 'exclude' => ['sometimes', 'nullable', 'string', 'max:100']];
    }

    /**
     * 轉換為不依賴HTTP的選項條件。
     *
     * @return IssueOptionQueryData 驗證後的查詢條件
     */
    public function toDto(): IssueOptionQueryData
    {
        $data = $this->validated();

        return new IssueOptionQueryData($this->pagination(), $data['kind'] ?? 'issue', $data['exclude'] ?? '');
    }
}
