<?php

namespace App\Data;

/** 已驗證的標籤操作, 操作名稱由伺服器路由提供 */
final readonly class TagWriteData
{
    /** 保留路由操作與經驗證的寫入輸入
     *
     * @param  string  $action  create／update／delete／apply
     * @param  array<string,mixed>  $input  有界欄位與 requestId
     */
    public function __construct(public string $action, public array $input) {}
}
