<?php

namespace App\Http\Requests;

use App\Data\TagWriteData;
use App\Domain\DomainError;
use Illuminate\Contracts\Validation\Validator;

/** Session／PAT 共用標籤 allowlist, 不接受操作者或租戶欄位 */
class TagWriteRequest extends JsonOperationRequest
{
    /** 驗證 JSON 與未知欄位, 不忽略錯字
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $input = $this->payload();
        $action = $this->route('tagAction');
        $allowed = match ($action) {
            'create' => ['requestId', 'name', 'color', 'description'],
            'update' => ['requestId', 'expectedRevision', 'name', 'color', 'description'],
            'delete' => ['requestId', 'expectedRevision'],
            'apply' => ['requestId', 'expectedRevision', 'addTagIds', 'removeTagIds'],
            default => [],
        };
        if (array_diff(array_keys($input), $allowed) || ($action === 'update' && ! array_intersect(array_keys($input), ['name', 'color', 'description']))) {
            DomainError::fail('INVALID_INPUT', '標籤包含不支援的欄位或沒有修改內容');
        }
        $this->replace($input);
    }

    /** 限制標籤定義與卡片差量的欄位及大小
     *
     * @return array<string,array> 欄位與有界差量規則 */
    public function rules(): array
    {
        $action = $this->route('tagAction');
        $base = ['requestId' => ['required', 'uuid'], ...($action !== 'create' ? ['expectedRevision' => ['required', 'integer:strict', 'min:1']] : [])];
        if ($action === 'delete') {
            return $base;
        }
        if ($action === 'apply') {
            return [...$base, 'addTagIds' => ['sometimes', 'array', 'list', 'max:20'], 'addTagIds.*' => ['required', 'uuid', 'distinct'], 'removeTagIds' => ['sometimes', 'array', 'list', 'max:20'], 'removeTagIds.*' => ['required', 'uuid', 'distinct']];
        }

        return [...$base, 'name' => [$action === 'create' ? 'required' : 'sometimes', 'string', 'min:1', 'max:40'], 'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'], 'description' => ['sometimes', 'string', 'max:300']];
    }

    /** 將已驗證欄位轉成 HTTP 無關的操作輸入
     *
     * @return TagWriteData 已驗證的 HTTP 無關輸入 */
    public function toDto(): TagWriteData
    {
        return new TagWriteData($this->route('tagAction'), $this->validated());
    }

    /** 維持受控驗證錯誤且不回顯輸入
     *
     * @param  Validator  $validator  Laravel 驗證結果
     * @return never 維持 400 錯誤契約, 不輸出輸入原文
     */
    protected function failedValidation(Validator $validator): never
    {
        DomainError::fail('INVALID_INPUT', '請確認標籤欄位、expectedRevision 與 requestId');
    }
}
