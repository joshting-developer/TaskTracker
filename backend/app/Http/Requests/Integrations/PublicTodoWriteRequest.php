<?php

namespace App\Http\Requests\Integrations;

use App\Domain\DomainError;
use App\Http\Requests\JsonOperationRequest;
use Illuminate\Contracts\Validation\Validator;

/**
 * TT-311：公開API的小待辦寫入；欄位規則與網頁相同, 實際權限與狀態規則由Todos用例判斷。
 */
class PublicTodoWriteRequest extends JsonOperationRequest
{
    /**
     * 以JSON本文取代表單輸入, 讓驗證只看API送來的欄位。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->replace($this->payload());
    }

    /**
     * 依路由決定需要的欄位：建立不需版本, 其餘都要expectedRevision。
     *
     * @return array<string,array<int,string>>
     */
    public function rules(): array
    {
        $base = ['requestId' => ['required', 'uuid']];
        $content = ['title' => ['sometimes', 'string', 'min:1', 'max:180'], 'note' => ['sometimes', 'string', 'max:2000'], 'content' => ['sometimes', 'string', 'max:6000']];
        if ($this->routeIs('public.todos.create')) {
            return [...$base, ...$content, 'assigneeId' => ['sometimes', 'string', 'max:100']];
        }
        $versioned = [...$base, 'expectedRevision' => ['required', 'integer:strict', 'min:1']];

        return $this->routeIs('public.todos.update') ? [...$versioned, ...$content] : [...$versioned, 'note' => ['sometimes', 'string', 'max:2000']];
    }

    /**
     * 統一回INVALID_INPUT, 不洩漏內部驗證細節。
     *
     * @param  Validator  $validator  驗證結果
     * @return never
     */
    protected function failedValidation(Validator $validator): never
    {
        DomainError::fail('INVALID_INPUT', '請確認待辦欄位、expectedRevision與requestId');
    }
}
