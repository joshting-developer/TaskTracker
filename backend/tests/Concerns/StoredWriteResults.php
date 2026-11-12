<?php

namespace Tests\Concerns;

use App\Models\Project;
use App\Repositories\RecordStore;

/**
 * TT-178後Web RPC寫入只回有界結果；需要整張卡片或工時內容的測試，改從資料入口讀回目前紀錄（即舊版完整結果的來源）。
 */
trait StoredWriteResults
{
    /**
     * 依寫入結果讀回目前的完整卡片紀錄。
     *
     * @param  array{id:string,projectId:string}  $result  寫入回傳的有界結果
     * @return array<string,mixed> 卡片紀錄（含留言與歷史）
     */
    protected function storedIssue(array $result): array
    {
        return $this->storeFor($result['projectId'])->require('issues', $result['id']);
    }

    /**
     * 依寫入結果讀回目前的完整工時紀錄。
     *
     * @param  array{id:string,projectId:string}  $result  寫入回傳的有界結果
     * @return array<string,mixed> 工時紀錄
     */
    protected function storedTimeEntry(array $result): array
    {
        return $this->storeFor($result['projectId'])->require('timeEntries', $result['id']);
    }

    /**
     * Project所屬工作區的資料入口。
     *
     * @param  string  $projectId  Project公開ID
     * @return RecordStore 限定工作區的資料入口
     */
    private function storeFor(string $projectId): RecordStore
    {
        return (new RecordStore)->forWorkspace(Project::where('public_id', $projectId)->firstOrFail()->workspace->public_id);
    }
}
