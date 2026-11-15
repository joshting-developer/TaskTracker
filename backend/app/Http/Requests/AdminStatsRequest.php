<?php

namespace App\Http\Requests;

use App\Data\AdminStatsQueryData;
use Illuminate\Foundation\Http\FormRequest;

/** 只允許固定期間及有界工作區游標, 不提供任意SQL篩選 */
class AdminStatsRequest extends FormRequest
{
    /** 資料資格由當次管理Session在服務內重驗
     *
     * @return bool 資料資格由當次管理Session在服務內重驗
     */
    public function authorize(): bool
    {
        return true;
    }

    /** 最多30日, 不接受深OFFSET
     *
     * @return array<string,list<string>> 最多30日, 不接受深OFFSET
     */
    public function rules(): array
    {
        return ['days' => ['sometimes', 'integer', 'in:7,30'], 'before' => ['sometimes', 'integer', 'min:1', 'max:9007199254740991']];
    }

    /** 驗證過的期間與游標
     *
     * @return AdminStatsQueryData 驗證過的期間與游標
     */
    public function toDto(): AdminStatsQueryData
    {
        return new AdminStatsQueryData((int) $this->validated('days', 7), $this->has('before') ? (int) $this->validated('before') : null);
    }
}
