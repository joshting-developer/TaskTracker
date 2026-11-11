<?php

namespace App\Http\Requests;

use App\Data\PageQueryData;
use App\Domain\DomainError;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** 驗證可公開延伸的有界列表參數；權限仍在交易內重驗。 */
class PageQueryRequest extends FormRequest
{
    /** 維持不可見資源404與缺少操作權403的差異。 */
    public function authorize(): bool
    {
        return true;
    }

    /** 僅接受此查詢需要的欄位與有界值。 */
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1', 'max:1000000'], 'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'], 'search' => ['sometimes', 'nullable', 'string', 'max:200']];
    }

    /** 將已驗證的頁碼與搜尋字詞轉成不可變條件。 */
    public function pagination(): PageQueryData
    {
        $data = $this->validated();

        return new PageQueryData((int) ($data['page'] ?? 1), (int) ($data['perPage'] ?? 20), trim($data['search'] ?? ''));
    }

    /** 維持既有INVALID_INPUT與400錯誤契約。 */
    protected function failedValidation(Validator $validator): void
    {
        DomainError::fail('INVALID_INPUT', '查詢條件格式不正確');
    }
}
