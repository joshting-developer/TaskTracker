<?php

namespace App\Repositories;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** TT-250：讀取Project的卡片刪除墓碑事件，依事件id遞增游標分頁。 */
class IssueDeletionReadRepository
{
    /**
     * 只讀同Workspace／Project的issueDeletions事件，多取一筆判斷下一頁；不讀其他稽核串流。
     *
     * @param  Project  $project  已授權Project
     * @param  int|null  $after  只取事件id大於此值
     * @param  int  $limit  每頁筆數
     * @return Collection<int,object{id:int,event_data:string}> 最多limit+1筆
     */
    public function page(Project $project, ?int $after, int $limit): Collection
    {
        return DB::table('audit_events')->where('workspace_id', $project->workspace_id)->where('entity_type', 'projects')
            ->where('entity_id', $project->id)->where('stream', 'issueDeletions')
            ->when($after !== null, fn ($q) => $q->where('id', '>', $after))
            ->orderBy('id')->limit($limit + 1)->get(['id', 'event_data']);
    }
}
