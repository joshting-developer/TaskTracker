<?php

namespace App\Http\Requests\Writes;

use App\Data\Writes\TimeEntryWriteData;
use App\Domain\DomainError;
use App\Http\Requests\JsonOperationRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * Web RPC工時寫入的傳輸邊界：只檢查JSON物件與欄位型別；必填、日期、每日上限與作者規則仍由授權之後的TimeEntryService判斷。
 */
class TimeEntryWriteRequest extends JsonOperationRequest
{
    /**
     * 驗證前先確認是JSON物件並在大小上限內。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->replace($this->payload());
    }

    /**
     * 依路徑上的RPC操作取得型別規則。
     *
     * @return array<string,list<string>> 驗證規則
     */
    public function rules(): array
    {
        $base = ['requestId' => ['sometimes', 'nullable', 'string']];
        $existing = ['id' => ['sometimes', 'nullable', 'string'], 'expectedRevision' => ['sometimes', 'nullable', 'integer']];
        $fields = ['issueId' => ['sometimes', 'nullable', 'string'], 'workDate' => ['sometimes', 'nullable', 'string'], 'minutes' => ['sometimes', 'nullable', 'integer'], 'note' => ['sometimes', 'nullable', 'string']];

        return match ($this->operation()) {
            'voidTime' => [...$base, ...$existing],
            'updateTime' => [...$base, ...$existing, ...$fields],
            default => [...$base, ...$fields, 'issueId' => ['sometimes', 'nullable', 'string']],
        };
    }

    /**
     * 轉成型別化輸入；原始輸入完整保留。
     *
     * @return TimeEntryWriteData 工時寫入輸入
     */
    public function toDto(): TimeEntryWriteData
    {
        $input = $this->payload();
        $integer = fn (string $key) => array_key_exists($key, $input) && $input[$key] !== null ? (int) $input[$key] : null;

        return new TimeEntryWriteData($this->operation(), (string) ($input['requestId'] ?? ''), $input['id'] ?? null, $integer('expectedRevision'),
            $input['issueId'] ?? null, $input['workDate'] ?? null, $integer('minutes'), $input['note'] ?? null, $input);
    }

    /**
     * 型別錯誤統一回INVALID_INPUT。
     *
     * @param  Validator  $validator  驗證結果
     * @return never
     *
     * @throws DomainError 欄位型別不符
     */
    protected function failedValidation(Validator $validator): never
    {
        DomainError::fail('INVALID_INPUT', '請確認工時欄位型別、expectedRevision與requestId');
    }

    /**
     * 目前路徑的RPC操作名稱。
     *
     * @return string logTime、updateTime或voidTime
     */
    private function operation(): string
    {
        return basename($this->path());
    }
}
