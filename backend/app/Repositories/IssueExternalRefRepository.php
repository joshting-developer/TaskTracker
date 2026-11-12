<?php

namespace App\Repositories;

use App\Models\Issue;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/** 已授權Project範圍內的外部識別對照；只做限定scope的查詢與新增，不判斷權限或業務規則。 */
class IssueExternalRefRepository
{
    /**
     * 以Project內部鍵限定租戶與專案，查外部識別對應的卡片公開ID。
     *
     * @param  Project  $project  已授權Project
     * @param  string  $source  外部來源代號
     * @param  string  $externalId  外部識別
     * @return string|null 卡片公開ID；未綁定時null
     */
    public function issueId(Project $project, string $source, string $externalId): ?string
    {
        return DB::table('issue_external_refs')->join('issues', 'issues.id', '=', 'issue_external_refs.issue_id')
            ->where('issue_external_refs.workspace_id', $project->workspace_id)->where('issue_external_refs.project_id', $project->id)
            ->where('issue_external_refs.source', $source)->where('issue_external_refs.external_id', $externalId)
            ->value('issues.public_id');
    }

    /**
     * 綁定同Project內剛建立的卡片；唯一索引是並行重複建立的最後防線。
     *
     * @param  Project  $project  已授權Project
     * @param  string  $issueId  同Project卡片公開ID
     * @param  string  $source  外部來源代號
     * @param  string  $externalId  外部識別
     * @return void
     */
    public function link(Project $project, string $issueId, string $source, string $externalId): void
    {
        $issue = Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->where('public_id', $issueId)->value('id');
        DB::table('issue_external_refs')->insert([
            'workspace_id' => $project->workspace_id, 'project_id' => $project->id, 'issue_id' => $issue,
            'source' => $source, 'external_id' => $externalId, 'created_at' => now(),
        ]);
    }

    /**
     * 已綁定卡片的有界摘要，供批次createOnly回報既有卡片。
     *
     * @param  Project  $project  已授權Project
     * @param  string  $issueId  同Project卡片公開ID
     * @return array{id:string,projectId:string,identifier:string,revision:int} 有界結果
     */
    public function summary(Project $project, string $issueId): array
    {
        $issue = Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->where('public_id', $issueId)->sole(['identifier', 'revision']);

        return ['id' => $issueId, 'projectId' => $project->public_id, 'identifier' => $issue->identifier, 'revision' => (int) $issue->revision];
    }
}
