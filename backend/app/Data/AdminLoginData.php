<?php

namespace App\Data;

/** 獨立管理登入的已驗證輸入, 不接受一般帳號或Workspace資格 */
final readonly class AdminLoginData
{
    /** 保存本次登入輸入, 不提供序列化輸出
     *
     * @param  string  $username  管理帳號名稱
     * @param  string  $password  此次輸入, 不保存或輸出
     */
    public function __construct(public string $username, public string $password) {}
}
