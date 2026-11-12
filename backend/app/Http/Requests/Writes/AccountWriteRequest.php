<?php

namespace App\Http\Requests\Writes;

use App\Data\Writes\AccountWriteData;

/** Account專用傳輸邊界；未知欄位保留原始receipt簽章，不直接mass assignment。 */
class AccountWriteRequest extends DomainWriteRequest
{
    /**
     * 只檢查傳輸型別，必填／權限／業務上限留在交易內。
     *
     * @return array<string,list<string>> 本領域欄位型別
     */
    public function rules(): array
    {
        return [...$this->commonRules(), 'name' => ['sometimes', 'nullable', 'string'], 'email' => ['sometimes', 'nullable', 'string'], 'password' => ['sometimes', 'nullable', 'string'], 'workspaceName' => ['sometimes', 'nullable', 'string'], 'workspaceId' => ['sometimes', 'nullable', 'string'], 'inviteToken' => ['sometimes', 'nullable', 'string']];
    }

    /**
     * 建立型別化領域輸入，保留原始JSON與缺漏／null差異。
     *
     * @return AccountWriteData 輸入DTO
     */
    public function toDto(): AccountWriteData
    {
        $input = $this->payload();

        return new AccountWriteData($this->operation(), (string) ($input['requestId'] ?? ''), $input['id'] ?? null, isset($input['expectedRevision']) ? (int) $input['expectedRevision'] : null, $input['name'] ?? null, $input['email'] ?? null, $input['password'] ?? null, $input['workspaceName'] ?? null, $input['workspaceId'] ?? null, $input['inviteToken'] ?? null, $input);
    }
}
