<?php

namespace App\Http\Requests\Writes;

/** 看板排序只接受目標看板與完整順序, 不接受自稱租戶或操作者 */
class BoardPositionRequest extends DomainWriteRequest
{
    /** 型別與數量上限在這裡擋下, 卡片是否在看板內留給交易內判斷
     * @return array<string,list<string>> 傳輸規則
     */
    public function rules(): array
    {
        return ['projectId' => ['required', 'string', 'max:100'], 'boardId' => ['required', 'string', 'max:100'], 'issueIds' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'issueIds.*' => ['required', 'string', 'max:100'], 'requestId' => ['required', 'uuid'], 'workspaceId' => ['prohibited'], 'actorId' => ['prohibited']];
    }
}
