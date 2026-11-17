<?php

namespace App\Http\Requests\Integrations;

use App\Data\Writes\TimeEntryWriteData;
use App\Domain\DomainError;
use App\Http\Requests\JsonOperationRequest;
use Illuminate\Contracts\Validation\Validator;

/** 工時公開輸入不接受作者、工作空間或路徑Project覆寫 */
class PublicTimeWriteRequest extends JsonOperationRequest
{
    /** 檢查JSON與allowlist, 未知欄位不得被靜默忽略
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $input = $this->payload();
        $allowed = array_keys($this->rules());
        if (array_diff(array_keys($input), $allowed)) {
            DomainError::fail('INVALID_INPUT', '工時包含不支援的欄位');
        }
        $this->replace($input);
    }

    /** 日期與每日合計仍由共用業務服務判斷
     * @return array 欄位規則
     */
    public function rules(): array
    {
        $create = $this->routeIs('public.time.create');
        $base = ['requestId' => ['required', 'uuid'], ...($create ? ['issueId' => ['required', 'string', 'max:100']] : ['expectedRevision' => ['required', 'integer:strict', 'min:1']])];
        if ($this->routeIs('public.time.void')) {
            return $base;
        }

        return [...$base, 'workDate' => ['required', 'date_format:Y-m-d'], 'minutes' => ['required', 'integer:strict', 'min:1', 'max:1440'], 'note' => ['sometimes', 'string', 'max:1000']];
    }

    /** 重用既有工時DTO, 路徑ID不能由JSON覆寫
     * @return TimeEntryWriteData 已驗證欄位與固定操作
     */
    public function toDto(): TimeEntryWriteData
    {
        $operation = match ($this->route()->getName()) {
            'public.time.create' => 'logTime',
            'public.time.update' => 'updateTime',
            'public.time.void' => 'voidTime',
        };
        $id = $this->route('entry');
        $input = [...$this->validated(), ...($id ? ['id' => $id] : [])];

        return new TimeEntryWriteData($operation, $input['requestId'], $id, $input['expectedRevision'] ?? null,
            $input['issueId'] ?? null, $input['workDate'] ?? null, $input['minutes'] ?? null, $input['note'] ?? null, $input);
    }

    /** 保持標準錯誤格式
     * @param  Validator  $validator  傳輸驗證結果
     * @return never
     */
    protected function failedValidation(Validator $validator): never
    {
        DomainError::fail('INVALID_INPUT', '請確認工時日期、分鐘數、expectedRevision與requestId');
    }
}
