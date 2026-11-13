<?php

namespace App\Http\Requests;

/**
 * TT-283：看板列表的分頁查詢, 另外可指定看使用中或已封存的看板。
 */
class BoardPageQueryRequest extends PageQueryRequest
{
    /**
     * 在分頁條件外加上archived（1／0／true／false）。
     *
     * @return array<string,array<int,string>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'archived' => ['sometimes', 'boolean']];
    }

    /**
     * 是否查已封存的看板。
     *
     * @return bool 已封存時為true
     */
    public function archived(): bool
    {
        return filter_var($this->validated()['archived'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
