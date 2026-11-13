<?php

namespace App\Http\Requests\Attachments;

use App\Domain\DomainError;
use App\Http\Requests\JsonOperationRequest;
use Illuminate\Contracts\Validation\Validator;

/** 附件版本與綁定條件的JSON邊界 */
class AttachmentChangeRequest extends JsonOperationRequest
{
    /** 沿用JSON大小／物件限制
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->replace($this->payload());
    }

    /** 綁定與刪除的必要欄位
     * @return array 驗證規則
     */
    public function rules(): array
    {
        return ['requestId' => ['required', 'uuid'], 'expectedRevision' => ['required', 'integer:strict', 'min:1'], 'issueId' => ['sometimes', 'string', 'max:100'], 'issueRevision' => ['sometimes', 'integer:strict', 'min:1']];
    }

    /** 統一錯誤格式
     * @param  Validator  $validator  驗證結果
     * @return never
     */
    protected function failedValidation(Validator $validator): void
    {
        DomainError::fail('INVALID_INPUT', '附件操作格式不正確');
    }
}
