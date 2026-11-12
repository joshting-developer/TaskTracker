<?php

namespace App\Http\Requests;

use App\Data\PageQueryData;
use App\Data\ProjectTimeQueryData;
use App\Domain\DomainError;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** 專案工時月曆與當日明細的GET allowlist；身分與資格在交易內重驗。 */
class ProjectTimeQueryRequest extends FormRequest
{
    /**
     * 維持不可見資源404與缺少操作權403的差異。
     *
     * @return bool 一律交由交易內授權
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 只接受月曆或當日查詢需要的欄位。
     *
     * @return array<string,array<int,string>> 驗證規則
     */
    public function rules(): array
    {
        return ['month' => ['sometimes', 'string', 'size:7'], 'date' => ['sometimes', 'string', 'size:10'], 'member' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'], 'perPage' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }

    /**
     * 月曆摘要條件，月份必填。
     *
     * @return ProjectTimeQueryData 整月範圍
     */
    public function calendar(): ProjectTimeQueryData
    {
        $data = $this->validated();

        return ProjectTimeQueryData::month($data['month'] ?? DomainError::fail('INVALID_INPUT', '請指定月份'), (string) ($data['member'] ?? ''));
    }

    /**
     * 當日明細條件，日期必填並分頁。
     *
     * @return ProjectTimeQueryData 單日範圍
     */
    public function day(): ProjectTimeQueryData
    {
        $data = $this->validated();

        return ProjectTimeQueryData::day($data['date'] ?? DomainError::fail('INVALID_INPUT', '請指定日期'), (string) ($data['member'] ?? ''),
            new PageQueryData((int) ($data['page'] ?? 1), (int) ($data['perPage'] ?? 20)));
    }

    /**
     * 維持既有INVALID_INPUT與400錯誤契約。
     *
     * @param  Validator  $validator  驗證結果
     * @return void
     */
    protected function failedValidation(Validator $validator): void
    {
        DomainError::fail('INVALID_INPUT', '查詢條件格式不正確');
    }
}
