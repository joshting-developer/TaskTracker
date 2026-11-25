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
            if (count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
                $fail('Duplicate widget');
            }
        }], 'widgets.*' => [function ($attribute, $value, $fail) {
            if (is_string($value) && in_array($value, DashboardPreferenceService::WIDGETS, true)) {
                return;
            }
            $rules = [
                'id' => ['required', 'string', 'regex:/^[a-zA-Z0-9-]{1,64}$/'],
                'kind' => ['required', 'in:'.implode(',', DashboardPreferenceService::WIDGETS)],
                'width' => ['required', 'integer', 'between:1,3'],
                'columns' => ['sometimes', 'integer', 'between:3,12'],
                'height' => ['required', 'integer', 'between:180,1000'],
                'title' => ['sometimes', 'nullable', 'string', 'max:100'],
                'filters' => ['sometimes', 'array:projectId,state,priority,assignee,tagId,dueFrom,dueTo'],
                ...PersonalDashboardRequest::filterRules('filters.'),
            ];
            if (! is_array($value) || array_diff(array_keys($value), array_keys(array_filter($rules, fn ($key) => ! str_contains($key, '.'), ARRAY_FILTER_USE_KEY))) || \Illuminate\Support\Facades\Validator::make($value, $rules)->fails()) {
                $fail('Invalid widget');
            }
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
