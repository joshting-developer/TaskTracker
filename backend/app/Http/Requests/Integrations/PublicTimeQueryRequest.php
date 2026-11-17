<?php

namespace App\Http\Requests\Integrations;

use App\Data\ProjectTimeQueryData;
use App\Domain\DomainError;
use App\Http\Requests\PageQueryRequest;
use DateTimeImmutable;

/** 本人工時每頁至多100筆, 每次日期範圍至多366天 */
class PublicTimeQueryRequest extends PageQueryRequest
{
    /** 限定查詢欄位與深分頁成本
     * @return array 驗證規則
     */
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'], 'from' => ['sometimes', 'date_format:Y-m-d'], 'to' => ['sometimes', 'date_format:Y-m-d'], 'issueId' => ['sometimes', 'string', 'max:100']];
    }

    /** 以台北工作日預設本月, 不接受任意作者參數
     * @return ProjectTimeQueryData 有界查詢條件
     */
    public function toDto(): ProjectTimeQueryData
    {
        if (array_diff(array_keys($this->query()), array_keys($this->rules()))) {
            DomainError::fail('INVALID_INPUT', '工時查詢包含不支援的欄位');
        }
        $data = $this->validated();
        $from = $data['from'] ?? now('Asia/Taipei')->startOfMonth()->toDateString();
        $to = $data['to'] ?? now('Asia/Taipei')->toDateString();
        if ($from > $to || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 365) {
            DomainError::fail('INVALID_INPUT', '工時查詢範圍須為1至366天');
        }

        return new ProjectTimeQueryData($from, $to, '', $this->pagination());
    }
}
