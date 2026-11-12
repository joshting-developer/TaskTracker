<?php

namespace App\Models;

/** Sanctum個人Token；write隱含read，撤銷資料保留供管理頁顯示。 */
class PersonalAccessToken extends \Laravel\Sanctum\PersonalAccessToken
{
    protected $guarded = ['id'];

    protected $hidden = ['token', 'issuance_request_id'];

    /**
     * 欄位型別轉換：權限清單與時間欄位。
     *
     * @return array<string,string> 日期及Scope的儲存型別
     */
    protected function casts(): array
    {
        return [...parent::casts(), 'revoked_at' => 'datetime'];
    }

    /**
     * 將write視為包含read，能力仍須與用例的有效permission取交集。
     *
     * @param  string  $ability  需要的Token能力
     * @return bool 是否有此能力，不代表資源授權
     */
    public function can($ability): bool
    {
        return ($ability === 'read' && parent::can('write')) || parent::can($ability);
    }
}
