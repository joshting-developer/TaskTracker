<?php

namespace App\Http\Requests\Integrations;

use App\Domain\DomainError;
use App\Http\Requests\JsonOperationRequest;
use Illuminate\Contracts\Validation\Validator;

/** 公開看板寫入只接受明確欄位, 不提供封存或整包收錄覆蓋 */
class PublicBoardWriteRequest extends JsonOperationRequest
{
    /**
     * 拒絕未知欄位與過大的JSON, 避免看似成功但輸入被忽略
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $input = $this->payload();
        $allowed = $this->routeIs('public.boards.create')
            ? ['requestId', 'name', 'description']
            : ['requestId', 'expectedRevision', 'name', 'description', 'addIssueIds', 'removeIssueIds'];
        if (array_diff(array_keys($input), $allowed)) {
            DomainError::fail('INVALID_INPUT', '看板包含不支援的欄位');
        }
        if (! $this->routeIs('public.boards.create') && ! array_intersect(array_keys($input), ['name', 'description', 'addIssueIds', 'removeIssueIds'])) {
            DomainError::fail('INVALID_INPUT', '請指定要調整的看板欄位');
        }
        $this->replace($input);
    }

    /**
     * 限制單次收錄差量和部分更新欄位
     *
     * @return array<string,array> HTTP欄位規則
     */
    public function rules(): array
    {
        $create = $this->routeIs('public.boards.create');

        return [
            'requestId' => ['required', 'uuid'],
            'name' => [$create ? 'required' : 'sometimes', 'string', 'min:1', 'max:60'],
            'description' => ['sometimes', 'string', 'max:300'],
            ...($create ? [] : [
                'expectedRevision' => ['required', 'integer:strict', 'min:1'],
                'addIssueIds' => ['sometimes', 'array', 'list', 'max:200'],
                'addIssueIds.*' => ['required', 'string', 'max:100', 'distinct'],
                'removeIssueIds' => ['sometimes', 'array', 'list', 'max:200'],
                'removeIssueIds.*' => ['required', 'string', 'max:100', 'distinct'],
            ]),
        ];
    }

    /**
     * 保持現有400錯誤契約, 不回顯輸入值
     *
     * @param  Validator  $validator  HTTP驗證結果
     * @return never
     */
    protected function failedValidation(Validator $validator): never
    {
        DomainError::fail('INVALID_INPUT', '請確認看板欄位、expectedRevision與requestId');
    }
}
