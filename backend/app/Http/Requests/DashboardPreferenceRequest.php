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
        return ['widgets' => ['present', 'array', 'list', 'max:'.count(DashboardPreferenceService::WIDGETS)], 'widgets.*' => ['string', 'distinct', 'in:'.implode(',', DashboardPreferenceService::WIDGETS)], 'expectedRevision' => ['required', 'integer', 'min:0']];
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
