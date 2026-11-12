<?php

namespace App\Http\Requests\Integrations;

use App\Domain\DomainError;
use App\Http\Requests\JsonOperationRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/** 公開Issue輸入allowlist，路徑資源／操作者不取自payload，PATCH保留省略欄位。 */
class PublicIssueWriteRequest extends JsonOperationRequest
{
    /** @return void 只驗JSON物件／大小，保留省略語意。 */
    protected function prepareForValidation(): void
    {
        $this->replace($this->payload());
    }

    /** @return array HTTP欄位與限制；跨資源規則交既有業務Service。 */
    public function rules(): array
    {
        $base = ['requestId' => ['required', 'uuid']];
        if ($this->routeIs('public.issues.comment')) {
            return [...$base, 'expectedRevision' => ['required', 'integer:strict', 'min:1'], 'content' => ['required', 'string', 'max:2000']];
        }
        $create = $this->routeIs('public.issues.create');

        return [...$base, ...($create ? [] : ['expectedRevision' => ['required', 'integer:strict', 'min:1']]),
            'title' => [$create ? 'required' : 'sometimes', 'string', 'min:1', 'max:180'],
            'description' => ['sometimes', 'string', 'max:6000'],
            'state' => ['sometimes', 'string', 'max:100'],
            'priority' => ['sometimes', Rule::in(['urgent', 'high', 'normal', 'low'])],
            'type' => ['sometimes', Rule::in(['epic', 'task', 'bug'])],
            'assignee' => ['sometimes', 'string', 'max:60'],
            'dueDate' => ['sometimes', 'string', 'max:10'],
            'epicId' => ['sometimes', 'string', 'max:100'],
            'parentIssueId' => ['sometimes', 'string', 'max:100'],
            'relatedIssueIds' => ['sometimes', 'array', 'max:200'],
            'relatedIssueIds.*' => ['string', 'max:100', 'distinct'],
            'estimateMinutes' => ['sometimes', 'integer:strict', 'min:0', 'max:525600'],
        ];
    }

    /**
     * 避免輸出驗證欄位原文，維持400 error envelope。
     *
     * @param  Validator  $validator  Laravel驗證結果
     * @return never 拒絕非法輸入
     */
    protected function failedValidation(Validator $validator): never
    {
        DomainError::fail('INVALID_INPUT', '請確認任務欄位、expectedRevision與requestId');
    }
}
