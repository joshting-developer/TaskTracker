<?php

namespace App\Http\Requests;

use App\Http\Requests\Writes\DomainWriteRequest;

/** 驗證操作傳輸邊界, 不從客戶端讀取帳號ID */
class EmailVerificationRequest extends DomainWriteRequest
{
    /** 限制驗證碼、連結與新信箱的輸入型別及長度
     * @return array<string,list<string>> 有界字串 */
    public function rules(): array
    {
        if ($this->routeIs('auth.verify-email')) {
            return ['code' => [in_array(config('app.env'), ['local', 'testing'], true) ? 'required_without:token' : 'prohibited', 'string', 'max:20', 'prohibits:token'], 'token' => ['required_without:code', 'string', 'regex:/^[a-f0-9]{64}$/', 'prohibits:code']];
        }

        return ['code' => ['sometimes', 'string', 'max:20'], 'token' => ['sometimes', 'string', 'max:64'], 'email' => ['sometimes', 'nullable', 'string', 'max:254']];
    }
}
