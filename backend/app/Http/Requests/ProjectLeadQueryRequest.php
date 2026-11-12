<?php

namespace App\Http\Requests;

/** 負責人候選的Project範圍，建立專案時可以不指定。 */
class ProjectLeadQueryRequest extends PageQueryRequest
{
    /**
     * 驗證有界分頁與可選Project公開ID。
     *
     * @return array<string,mixed> Laravel驗證規則
     */
    public function rules(): array
    {
        return [...parent::rules(), 'project' => ['sometimes', 'nullable', 'string', 'max:100']];
    }

    /**
     * 回傳已驗證的Project，不從輸入取得操作者。
     *
     * @return string|null null代表建立Project用途
     */
    public function projectId(): ?string
    {
        return $this->validated('project') ?: null;
    }
}
