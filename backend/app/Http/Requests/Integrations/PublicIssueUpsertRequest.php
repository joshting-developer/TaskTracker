<?php

namespace App\Http\Requests\Integrations;

use App\Services\Integrations\IssueExternalReferences;
use Illuminate\Validation\Validator;

/** TT-171：外部識別upsert輸入；欄位同PATCH，expectedRevision是否必要待查到既有卡片才判斷。 */
class PublicIssueUpsertRequest extends PublicIssueWriteRequest
{
    /**
     * 卡片欄位沿公開PATCH allowlist；建立時標題必填由既有業務規則檢查。
     *
     * @return array HTTP欄位與限制
     */
    public function rules(): array
    {
        return [...parent::rules(), 'expectedRevision' => ['sometimes', 'integer:strict', 'min:1']];
    }

    /**
     * 路徑外部識別格式不符時同樣回INVALID_INPUT；不從payload讀取。
     *
     * @return array<int,callable> 額外驗證
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! IssueExternalReferences::valid($this->source(), $this->externalId())) {
                $validator->errors()->add('externalId', 'invalid');
            }
        }];
    }

    /**
     * 路徑上的外部來源代號。
     *
     * @return string 來源代號
     */
    public function source(): string
    {
        return (string) $this->route('source');
    }

    /**
     * 路徑上的外部識別。
     *
     * @return string 外部識別
     */
    public function externalId(): string
    {
        return (string) $this->route('externalId');
    }

    /**
     * 卡片欄位與expectedRevision，不含requestId。
     *
     * @return array<string,mixed> 已驗證卡片欄位
     */
    public function fields(): array
    {
        return collect($this->validated())->except('requestId')->all();
    }
}
