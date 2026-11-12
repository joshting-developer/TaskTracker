<?php

namespace App\Http\Requests;

use App\Data\TimeReportData;
use App\Domain\DomainError;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** 工時查詢的HTTP allowlist，不從輸入取得操作者或租戶。 */
class TimeReportRequest extends JsonOperationRequest
{
    /**
     * JSON傳輸上限與物件檢查沿用共同邊界。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->replace($this->payload());
    }

    /**
     * 未登入仍維持原401；實際資格與permission在交易內重查。
     *
     * @return bool 授權由交易內的用例處理，這裡一律允許
     */
    public function authorize(): bool
    {
        if (! Auth::check()) {
            DomainError::fail('UNAUTHENTICATED', '請先登入');
        }

        return true;
    }

    /**
     * 工時分析條件的驗證規則：日期、Project、成員、分組與明細分頁。
     *
     * @return array 只允許查詢欄位；日期語意由不可變查詢條件共同驗證。
     */
    public function rules(): array
    {
        $pageRule = static function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_int($value) || $value < 1 || $value > 1000000) {
                $fail('頁碼超出範圍或不是整數');
            }
        };
        $withoutDetails = fn () => $this->input('detailGroup') === null;

        return [
            'startDate' => ['required', 'string', 'max:10'],
            'endDate' => ['required', 'string', 'max:10'],
            'projectId' => ['sometimes', 'string', 'max:100'],
            'userId' => ['sometimes', 'string', 'max:100'],
            'detailGroup' => ['sometimes', 'nullable', Rule::in(['dates', 'issues'])],
            'detailPage' => [Rule::excludeIf($withoutDetails), 'sometimes', $pageRule],
            'entryGroupKey' => [Rule::excludeIf($withoutDetails), 'sometimes', 'string', 'max:220'],
            'entryPage' => [Rule::excludeIf($withoutDetails), 'sometimes', $pageRule],
        ];
    }

    /**
     * 轉成不可變的工時分析條件。
     *
     * @return TimeReportData 格式及日期範圍已驗證的查詢。
     */
    public function toDto(): TimeReportData
    {
        return TimeReportData::from($this->validated());
    }

    /**
     * 保留400與既有錯誤envelope，不改為Laravel預設422。
     *
     * @param  Validator  $validator  驗證結果
     * @return void
     */
    protected function failedValidation(Validator $validator): void
    {
        DomainError::fail('INVALID_INPUT', '工時分析資料格式不正確');
    }
}
