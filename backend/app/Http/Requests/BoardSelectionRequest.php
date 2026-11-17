<?php

namespace App\Http\Requests;

use App\Data\BoardSelectionData;
use App\Domain\DomainError;
use App\Http\Requests\Concerns\BoardRuleFields;
use Illuminate\Contracts\Validation\Validator;

/** 純讀取預覽不需要 requestId, 不接受前端身分或任意查詢欄位 */
class BoardSelectionRequest extends JsonOperationRequest
{
    use BoardRuleFields;

    /** 拒絕未知的預覽輸入, 不建立暫存看板
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $input = $this->payload();
        if (array_diff(array_keys($input), ['autoInclude', 'selectedIssueIds', 'excludedIssueIds'])) {
            DomainError::fail('INVALID_INPUT', '看板預覽包含不支援的欄位');
        }
        $this->replace($input);
    }

    /** 限制預覽的條件及手動例外上限
     * @return array<string,array> 傳輸欄位
     */
    public function rules(): array
    {
        return [...$this->boardRuleFields(),
            'selectedIssueIds' => ['sometimes', 'array', 'list', 'max:1000'], 'selectedIssueIds.*' => ['string', 'max:100', 'distinct'],
            'excludedIssueIds' => ['sometimes', 'array', 'list', 'max:1000'], 'excludedIssueIds.*' => ['string', 'max:100', 'distinct'],
        ];
    }

    /** 只將已驗證的業務純值交給 Service
     * @return BoardSelectionData 預覽輸入
     */
    public function toDto(): BoardSelectionData
    {
        return new BoardSelectionData($this->boardRuleData(), $this->validated('selectedIssueIds', []), $this->validated('excludedIssueIds', []));
    }

    /** 維持既有錯誤 envelope, 不回顯使用者內容
     * @param  Validator  $validator  傳輸驗證結果
     * @return never
     */
    protected function failedValidation(Validator $validator): never
    {
        DomainError::fail('INVALID_INPUT', '看板收錄條件格式不正確');
    }
}
