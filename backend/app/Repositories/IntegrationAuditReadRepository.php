<?php

namespace App\Repositories;

use App\Models\IntegrationAudit;
use App\Models\Project;

/** TT-176：限定Workspace查卡片目前revision是否由公開API寫入產生；只讀來源metadata。 */
class IntegrationAuditReadRepository
{
    /**
     * 依(issue公開ID, revision)配對找產生該revision的Token寫入；沒有配對代表最新版本來自Web或更早資料。
     *
     * @param  Project  $project  已授權Project（取Workspace內部鍵）
     * @param  array<string,int>  $revisions  issue公開ID → 目前revision
     * @return array<string,array{tokenId:string|null,userId:string,clientReference:string|null}> issue公開ID → 寫入來源
     */
    public function tokenWrites(Project $project, array $revisions): array
    {
        if ($revisions === []) {
            return [];
        }
        $rows = IntegrationAudit::where('workspace_id', $project->workspace_id)->where('source', 'token')
            ->where(function ($q) use ($revisions) {
                foreach ($revisions as $issue => $revision) {
                    $q->orWhere(fn ($pair) => $pair->where('resource_id', $issue)->where('resource_revision', $revision));
                }
            })->orderBy('id')->get(['resource_id', 'token_id', 'actor_user_id', 'client_reference']);

        return $rows->mapWithKeys(fn ($row) => [$row->resource_id => ['tokenId' => $row->token_id, 'userId' => $row->actor_user_id, 'clientReference' => $row->client_reference]])->all();
    }
}
