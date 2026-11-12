<?php

namespace App\Http\Requests\Writes;

use App\Domain\DomainError;
use App\Http\Requests\JsonOperationRequest;
use Illuminate\Contracts\Validation\Validator;

/** 共用JSON傳輸型別檢查；資源授權、必填、revision與業務驗證仍在交易內。 */
abstract class DomainWriteRequest extends JsonOperationRequest
{
    /**
     * 只解析受限JSON物件，避免前端字串trim或空值轉換改寫簽章。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->replace($this->payload());
    }

    /**
     * 固定路由操作，永不讀body的operation。
     *
     * @return string 路由名稱
     */
    protected function operation(): string
    {
        return (string) ($this->route('operation') ?? basename($this->path()));
    }

    /**
     * 共用的傳輸型別規則；必填留給授權後領域服務。
     *
     * @return array<string,list<string>> JSON型別規則
     */
    protected function commonRules(): array
    {
        return ['id' => ['sometimes', 'nullable', 'string'], 'requestId' => ['sometimes', 'nullable', 'string'], 'expectedRevision' => ['sometimes', 'nullable', 'integer']];
    }

    /**
     * 維持DomainError對外錯誤格式，不洩漏Laravel驗證內部結構。
     *
     * @param  Validator  $validator  型別錯誤
     * @return never
     */
    protected function failedValidation(Validator $validator): never
    {
        DomainError::fail('INVALID_INPUT', '請確認欄位型別、expectedRevision與requestId');
    }
}
