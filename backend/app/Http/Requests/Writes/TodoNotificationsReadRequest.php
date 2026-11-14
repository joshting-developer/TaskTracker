<?php

namespace App\Http\Requests\Writes;

/** 通知全部已讀只接受操作識別碼, 範圍由伺服器決定 */
class TodoNotificationsReadRequest extends DomainWriteRequest
{
    /** 驗證唯一操作識別碼, 不接受自訂收件人與租戶
     * @return array<string,list<string>> 傳輸規則
     */
    public function rules(): array
    {
        return ['requestId' => ['required', 'uuid'], 'recipientId' => ['prohibited'], 'workspaceId' => ['prohibited']];
    }
}
