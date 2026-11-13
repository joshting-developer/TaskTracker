<?php

namespace App\Http\Requests;

use App\Data\TodoQueryData;
use Illuminate\Validation\Rule;

/** 待辦查詢allowlist；不能指定其他人的帳號或工作區。 */
class TodoQueryRequest extends PageQueryRequest
{
    /**
     * 驗證個人查詢及有界分頁。
     *
     * @return array<string,mixed> Laravel驗證規則
     */
    public function rules(): array
    {
        return [...parent::rules(), 'scope' => ['sometimes', Rule::in(['mine', 'sent', 'involved'])], 'state' => ['sometimes', Rule::in(['open', 'closed'])]];
    }

    /**
     * 將HTTP條件轉成用例輸入。
     *
     * @return TodoQueryData 已驗證條件
     */
    public function toDto(): TodoQueryData
    {
        $data = $this->validated();

        return new TodoQueryData($this->pagination(), $data['scope'] ?? 'mine', $data['state'] ?? 'open');
    }
}
