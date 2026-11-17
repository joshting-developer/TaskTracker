<?php

namespace App\Http\Requests\Concerns;

use App\Data\BoardRuleData;
use App\Domain\DomainError;
use Illuminate\Validation\Rule;

/** Session、Bearer 與預覽共用同一組有界規則格式 */
trait BoardRuleFields
{
    /** 列出唯一支援的條件欄位, 未指定的群組表示不限
     * @return array<string,array> 傳輸格式與上限
     */
    protected function boardRuleFields(): array
    {
        $raw = json_decode($this->getContent());
        if (property_exists($raw, 'autoInclude') && $raw->autoInclude !== null && ! $raw->autoInclude instanceof \stdClass) {
            DomainError::fail('INVALID_INPUT', '自動收錄條件必須是物件或 null');
        }

        return [
            'autoInclude' => ['sometimes', 'nullable', 'array:tagIds,types,states'],
            'autoInclude.tagIds' => ['sometimes', 'array', 'list', 'max:20'], 'autoInclude.tagIds.*' => ['uuid', 'distinct'],
            'autoInclude.types' => ['sometimes', 'array', 'list', 'max:3'], 'autoInclude.types.*' => [Rule::in(['epic', 'task', 'bug']), 'distinct'],
            'autoInclude.states' => ['sometimes', 'array', 'list', 'max:30'], 'autoInclude.states.*' => ['string', 'max:40', 'distinct'],
        ];
    }

    /** 將已驗證條件轉為純值, 原始缺漏與 null 仍由 receipt payload 保留
     * @return BoardRuleData|null 自動條件或手動模式
     */
    protected function boardRuleData(): ?BoardRuleData
    {
        $data = $this->validated('autoInclude');

        return $data === null ? null : new BoardRuleData($data['tagIds'] ?? [], $data['types'] ?? [], $data['states'] ?? []);
    }
}
