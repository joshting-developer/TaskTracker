<?php

namespace App\Http\Requests\Writes;

use App\Data\Writes\ProjectWriteData;

/** Project專用傳輸邊界；未知欄位保留原始receipt簽章，不直接mass assignment。 */
class ProjectWriteRequest extends DomainWriteRequest
{
    /**
     * 只檢查傳輸型別，必填／權限／業務上限留在交易內。
     *
     * @return array<string,list<string>> 本領域欄位型別
     */
    public function rules(): array
    {
        return [...$this->commonRules(), 'name' => ['sometimes', 'nullable', 'string'], 'code' => ['sometimes', 'nullable', 'string'], 'description' => ['sometimes', 'nullable', 'string'], 'leadId' => ['sometimes', 'nullable', 'string'], 'projectId' => ['sometimes', 'nullable', 'string'], 'statuses' => ['sometimes', 'nullable', 'array'], 'statuses.*' => ['array'], 'statuses.*.id' => ['sometimes', 'nullable', 'string'], 'statuses.*.label' => ['sometimes', 'nullable', 'string'], 'statuses.*.color' => ['sometimes', 'nullable', 'string']];
    }

    /**
     * 建立型別化領域輸入，保留原始JSON與缺漏／null差異。
     *
     * @return ProjectWriteData 輸入DTO
     */
    public function toDto(): ProjectWriteData
    {
        $input = $this->payload();

        return new ProjectWriteData($this->operation(), (string) ($input['requestId'] ?? ''), $input['id'] ?? null, isset($input['expectedRevision']) ? (int) $input['expectedRevision'] : null, $input['name'] ?? null, $input['code'] ?? null, $input['description'] ?? null, $input['leadId'] ?? null, $input['projectId'] ?? null, $input['statuses'] ?? null, $input);
    }
}
