<?php

namespace App\Http\Requests\Writes;

/** 限定資格狀態輸入, 不接受自稱租戶或操作者 */
class WorkspaceMemberStatusRequest extends DomainWriteRequest
{
    /** 驗證狀態、版本與操作識別碼, 資格留在交易內判斷
     * @return array<string,list<string>> 傳輸規則
     */
    public function rules(): array
    {
        return ['userId' => ['required', 'string', 'max:100'], 'status' => ['required', 'in:active,disabled'], 'expectedRevision' => ['required', 'integer', 'min:0'], 'requestId' => ['required', 'uuid'], 'workspaceId' => ['prohibited'], 'actorId' => ['prohibited']];
    }
}
