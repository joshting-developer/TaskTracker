<?php

namespace App\Http\Requests\Integrations;

use App\Domain\DomainError;
use App\Http\Requests\JsonOperationRequest;
use App\Services\Integrations\IssueExternalReferences;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Validation\Validator;

/** TT-172：外部識別批次upsert輸入；結構錯誤整批400，業務錯誤由Service逐項回報。 */
class PublicIssueBatchRequest extends JsonOperationRequest
{
    public const MAX_ITEMS = 50;

    /**
     * 驗證前先確認輸入是JSON物件且大小在上限內。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->replace($this->payload());
    }

    /**
     * 批次與項目欄位規則；卡片欄位沿公開單筆allowlist。
     *
     * @return array HTTP欄位與限制
     */
    public function rules(): array
    {
        $key = fn (string $prefix, string $presence) => [
            $prefix.'source' => [$presence, 'string', 'regex:'.IssueExternalReferences::SOURCE_PATTERN],
            $prefix.'externalId' => [$presence, 'string', 'regex:'.IssueExternalReferences::EXTERNAL_ID_PATTERN],
        ];

        return [
            'requestId' => ['required', 'uuid'],
            'createOnly' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['array'],
            ...$key('items.*.', 'required'),
            'items.*.expectedRevision' => ['sometimes', 'integer:strict', 'min:1'],
            'items.*.epicRef' => ['sometimes', 'array'],
            ...$key('items.*.epicRef.', 'required_with:items.*.epicRef'),
            'items.*.parentRef' => ['sometimes', 'array'],
            ...$key('items.*.parentRef.', 'required_with:items.*.parentRef'),
            ...PublicIssueWriteRequest::issueFields(false, 'items.*.'),
        ];
    }

    /**
     * 同一批次不可重複外部識別，也不可同時指定epicRef與epicId或parentRef與parentIssueId。
     *
     * @return array<int,callable> 額外驗證
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $seen = [];
            foreach ((array) $this->input('items', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $key = ($item['source'] ?? '')."\n".($item['externalId'] ?? '');
                if (isset($seen[$key]) || (isset($item['epicRef']) && isset($item['epicId'])) || (isset($item['parentRef']) && isset($item['parentIssueId']))) {
                    $validator->errors()->add('items.'.$index, 'conflict');
                }
                $seen[$key] = true;
            }
        }];
    }

    /**
     * 已驗證項目，只保留allowlist欄位。
     *
     * @return list<array<string,mixed>> 項目
     */
    public function items(): array
    {
        return array_values($this->validated()['items']);
    }

    /**
     * 是否只建立、不更新已綁定項目。
     *
     * @return bool createOnly
     */
    public function createOnly(): bool
    {
        return (bool) ($this->validated()['createOnly'] ?? false);
    }

    /**
     * 維持400 error envelope，不輸出欄位原文。
     *
     * @param  ValidatorContract  $validator  Laravel驗證結果
     * @return never 拒絕非法輸入
     */
    protected function failedValidation(ValidatorContract $validator): never
    {
        DomainError::fail('INVALID_INPUT', '請確認批次項目、外部識別與requestId（最多'.self::MAX_ITEMS.'項、同批不可重複）');
    }
}
