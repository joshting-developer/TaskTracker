<?php

namespace App\Http\Requests;

use App\Data\AdminLoginData;
use Illuminate\Foundation\Http\FormRequest;

/** 管理登入格式驗證, 身分仍由獨立服務驗密碼 */
class AdminLoginRequest extends FormRequest
{
    /** 允許匿名提交登入, CSRF及限流仍在路由執行
     *
     * @return bool 允許匿名提交登入, CSRF及限流仍在路由執行
     */
    public function authorize(): bool
    {
        return true;
    }

    /** 有界帳密欄位
     *
     * @return array<string,list<string>> 有界帳密欄位
     */
    public function rules(): array
    {
        return ['username' => ['required', 'string', 'max:100'], 'password' => ['required', 'string', 'max:1024']];
    }

    /** 驗證過的輸入
     *
     * @return AdminLoginData 驗證過的輸入
     */
    public function toDto(): AdminLoginData
    {
        return new AdminLoginData($this->validated('username'), $this->validated('password'));
    }
}
