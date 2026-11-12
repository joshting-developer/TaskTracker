<?php

namespace App\Http\Requests\Integrations;

use App\Domain\DomainError;
use App\Http\Requests\JsonOperationRequest;
use Illuminate\Contracts\Validation\Validator;

/** 僅驗證個人Token輸入，Actor與工作區從Session重新驗證。 */
class CreateTokenRequest extends JsonOperationRequest
{
    /** @return void 正規化JSON物件後交由FormRequest驗證，不讀可信資格。 */
    protected function prepareForValidation(): void
    {
        $this->replace($this->payload());
    }

    /** @return array<string,array> 有限Scope及固定日期格式 */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:60'], 'abilities' => ['required', 'array', 'min:1', 'max:2'],
            'abilities.*' => ['required', 'string', 'distinct', 'in:read,write'],
            'expiresAt' => ['sometimes', 'date_format:Y-m-d'], 'requestId' => ['required', 'uuid']];
    }

    /**
     * 以既有400 DomainError拒絕欄位，不輸出驗證原始值或Token。
     *
     * @param  Validator  $validator  Laravel驗證結果
     * @return never 拋出公開錯誤
     */
    protected function failedValidation(Validator $validator): never
    {
        DomainError::fail('INVALID_INPUT', '請確認Token名稱、Scope與到期日');
    }
}
