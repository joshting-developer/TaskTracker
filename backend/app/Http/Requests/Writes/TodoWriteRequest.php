<?php

namespace App\Http\Requests\Writes;

use App\Data\Writes\TodoWriteData;

/** Todo專用傳輸邊界；未知欄位保留原始receipt簽章，不直接mass assignment。 */
class TodoWriteRequest extends DomainWriteRequest
{
    /**
     * 只檢查傳輸型別，必填／權限／業務上限留在交易內。
     *
     * @return array<string,list<string>> 本領域欄位型別
     */
    public function rules(): array
    {
        return [...$this->commonRules(), 'title' => ['sometimes', 'nullable', 'string'], 'content' => ['sometimes', 'nullable', 'string'], 'note' => ['sometimes', 'nullable', 'string'], 'reply' => ['sometimes', 'nullable', 'string'], 'assigneeId' => ['sometimes', 'nullable', 'string'], 'issue' => ['sometimes', 'nullable', 'array'], 'issue.projectId' => ['sometimes', 'nullable', 'string']];
    }

    /**
     * 建立型別化領域輸入，保留原始JSON與缺漏／null差異。
     *
     * @return TodoWriteData 輸入DTO
     */
    public function toDto(): TodoWriteData
    {
        $input = $this->payload();

        return new TodoWriteData($this->operation(), (string) ($input['requestId'] ?? ''), $input['id'] ?? null, isset($input['expectedRevision']) ? (int) $input['expectedRevision'] : null, $input['title'] ?? null, $input['content'] ?? null, $input['note'] ?? null, $input['assigneeId'] ?? null, $input['issue'] ?? null, $input);
    }
}
