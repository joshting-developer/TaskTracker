<?php

namespace App\Http\Requests;

use App\Domain\DomainError;
use App\Services\DashboardPreferenceService;
use Illuminate\Contracts\Validation\Validator;

/** TT-186：首頁小工具順序只接受白名單內不重複的ID與讀到的版本 */
class DashboardPreferenceRequest extends JsonOperationRequest
{
    /** 拒絕白名單外欄位
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $input = $this->payload();
        if (array_diff(array_keys($input), ['widgets', 'expectedRevision', 'requestId'])) {
            DomainError::fail('INVALID_INPUT', '首頁設定包含不支援的欄位');
        }
        $this->replace($input);
    }

    /** 驗證規則
     * @return array 規則
     */
    public function rules(): array
    {
        return ['widgets' => ['present', 'array', 'list', 'max:20', function ($attribute, $value, $fail) {
            $ids = array_map(fn ($item) => is_array($item) ? ($item['id'] ?? '') : $item, $value);
            if (count(array_unique($ids, SORT_REGULAR)) !== count($ids)) { $fail('Duplicate widget'); }
        }], 'widgets.*' => [function ($attribute, $value, $fail) {
            if (is_string($value) && in_array($value, DashboardPreferenceService::WIDGETS, true)) { return; }
            if (! is_array($value) || array_diff(array_keys($value), ['id', 'kind', 'width', 'height']) || ! is_string($value['id'] ?? null) || ! preg_match('/^[a-zA-Z0-9-]{1,64}$/', $value['id']) || ! in_array($value['kind'] ?? '', DashboardPreferenceService::WIDGETS, true) || ! is_int($value['width'] ?? null) || $value['width'] < 1 || $value['width'] > 3 || ! is_int($value['height'] ?? null) || $value['height'] < 300 || $value['height'] > 1000) { $fail('Invalid widget'); }
        }], 'expectedRevision' => ['required', 'integer', 'min:0']];
    }

    /** 與API保持相同錯誤契約
     * @param  Validator  $validator  驗證結果
     * @return never
     */
    protected function failedValidation(Validator $validator): never
    {
        DomainError::fail('INVALID_INPUT', '首頁設定格式不正確');
    }
}
