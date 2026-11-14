<?php

namespace App\Http\Requests;

use App\Http\Requests\Writes\DomainWriteRequest;

/** 只接受重設必要欄位, 不接受使用者ID或redirect網址 */
class PasswordResetRequest extends DomainWriteRequest
{
    /**
     * 依申請或重設路由限制信箱、憑證與密碼確認欄位
     *
     * @return array<string,list<string>> 輸入型別與固定密碼政策
     */
    public function rules(): array
    {
        $rules = ['email' => ['required', 'string', 'email', 'max:254']];
        if ($this->routeIs('auth.reset-password')) {
            $rules += ['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'], 'password' => ['required', 'string', 'min:10', 'max:128', 'confirmed'], 'password_confirmation' => ['required', 'string', 'max:128']];
        }

        return $rules;
    }
}
