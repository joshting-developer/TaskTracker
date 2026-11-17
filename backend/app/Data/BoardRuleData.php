<?php

namespace App\Data;

/** 已驗證的自動收錄條件, 群組間 AND、類型與狀態群組內 OR */
final readonly class BoardRuleData
{
    /** 保存有界的同專案條件
     * @param  list<string>  $tagIds  全部必須符合的標籤
     * @param  list<string>  $types  允許的卡片類型
     * @param  list<string>  $states  允許的狀態代碼
     */
    public function __construct(public array $tagIds, public array $types, public array $states) {}

    /** 將純值條件交給既有 JSON 輸出與儲存邊界
     * @return array{tagIds:list<string>,types:list<string>,states:list<string>}
     */
    public function toArray(): array
    {
        return ['tagIds' => $this->tagIds, 'types' => $this->types, 'states' => $this->states];
    }
}
