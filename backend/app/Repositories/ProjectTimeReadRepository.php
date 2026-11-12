<?php

namespace App\Repositories;

use App\Data\ProjectTimeQueryData;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** 專案工時月曆的SQL聚合與當日明細；資格由Service先解析，這裡只套用已決定的範圍。 */
class ProjectTimeReadRepository
{
    /** 每個聚合清單的上限，避免大型專案月份回應無界成長。 */
    public const SUMMARY_LIMIT = 100;

    /**
     * 有效工時的共用範圍：同Workspace／Project、期間內、未作廢，必要時限定作者。
     *
     * @param  Project  $project  已授權專案
     * @param  ProjectTimeQueryData  $range  期間與成員條件
     * @param  string  $author  限定作者User ID，空字串代表不限
     * @return Builder 限定查詢
     */
    private function entries(Project $project, ProjectTimeQueryData $range, string $author): Builder
    {
        return DB::table('time_entries as entry')
            ->join('workspace_members as author', fn ($join) => $join->on('author.id', '=', 'entry.member_id')->on('author.workspace_id', '=', 'entry.workspace_id'))
            ->where('entry.workspace_id', $project->workspace_id)->where('entry.project_id', $project->id)
            ->whereNull('entry.voided_at')->whereBetween('entry.work_date', [$range->from, $range->to])
            ->when($author !== '', fn ($q) => $q->where('author.user_id', $author));
    }

    /**
     * 月曆摘要：每日、成員、卡片與Epic的SQL加總，不回傳原始工時列。
     *
     * @param  Project  $project  已授權專案
     * @param  ProjectTimeQueryData  $range  整月條件
     * @param  string  $author  限定作者User ID
     * @return array{days:list<array>,members:list<array>,issues:list<array>,epics:list<array>,total:int} 月份摘要
     */
    public function calendar(Project $project, ProjectTimeQueryData $range, string $author): array
    {
        $days = $this->entries($project, $range, $author)->groupBy('entry.work_date')->orderBy('entry.work_date')
            ->selectRaw('entry.work_date as date, SUM(entry.minutes) as minutes, COUNT(*) as entries')->get()
            ->map(fn ($row) => ['date' => substr((string) $row->date, 0, 10), 'minutes' => (int) $row->minutes, 'count' => (int) $row->entries])->all();
        $members = $this->entries($project, $range, $author)->join('users', 'users.id', '=', 'author.user_id')
            ->groupBy('users.id', 'users.name')->selectRaw('users.id as id, users.name as name, SUM(entry.minutes) as minutes')
            ->orderByDesc('minutes')->orderBy('users.id')->limit(self::SUMMARY_LIMIT)->get()
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'minutes' => (int) $row->minutes])->all();
        $issues = $this->entries($project, $range, $author)
            ->join('issues as target', fn ($join) => $join->on('target.id', '=', 'entry.issue_id')->on('target.project_id', '=', 'entry.project_id'))
            ->groupBy('target.id', 'target.public_id', 'target.identifier', 'target.title', 'target.type', 'target.estimate_minutes')
            ->selectRaw('target.public_id as id, target.identifier as identifier, target.title as title, target.type as type, target.estimate_minutes as estimate, SUM(entry.minutes) as spent')
            ->orderByDesc('spent')->orderBy('target.id')->limit(self::SUMMARY_LIMIT)->get()
            ->map(fn ($row) => ['id' => $row->id, 'identifier' => $row->identifier, 'title' => $row->title, 'type' => $row->type, 'estimateMinutes' => (int) $row->estimate, 'spent' => (int) $row->spent])->all();

        return ['days' => $days, 'members' => $members, 'issues' => $issues, 'epics' => $this->epics($project, $range, $author),
            'total' => array_sum(array_column($days, 'minutes'))];
    }

    /**
     * Epic工時：Epic本身與所屬卡片的有效工時各計一次；預估沿用所屬卡片加總。
     *
     * @param  Project  $project  已授權專案
     * @param  ProjectTimeQueryData  $range  整月條件
     * @param  string  $author  限定作者User ID
     * @return list<array<string,mixed>> 有投入的Epic摘要
     */
    private function epics(Project $project, ProjectTimeQueryData $range, string $author): array
    {
        $grouped = $this->entries($project, $range, $author)
            ->join('issues as target', fn ($join) => $join->on('target.id', '=', 'entry.issue_id')->on('target.project_id', '=', 'entry.project_id'))
            ->selectRaw("CASE WHEN target.type = 'epic' THEN target.id ELSE target.epic_id END as epic_key, entry.minutes as minutes");
        $estimate = DB::table('issues as child')->selectRaw('COALESCE(SUM(child.estimate_minutes), 0)')
            ->whereColumn('child.workspace_id', 'epic.workspace_id')->whereColumn('child.project_id', 'epic.project_id')->whereColumn('child.epic_id', 'epic.id');

        return DB::query()->fromSub($grouped, 'spent')->join('issues as epic', 'epic.id', '=', 'spent.epic_key')
            ->where('epic.workspace_id', $project->workspace_id)->where('epic.project_id', $project->id)->where('epic.type', 'epic')
            ->groupBy('epic.id', 'epic.public_id', 'epic.identifier', 'epic.title', 'epic.workspace_id', 'epic.project_id')
            ->select(['epic.public_id as id', 'epic.identifier as identifier', 'epic.title as title'])
            ->selectRaw('SUM(spent.minutes) as spent')->selectSub($estimate, 'estimate')
            ->orderByDesc('spent')->orderBy('epic.id')->limit(self::SUMMARY_LIMIT)->get()
            ->map(fn ($row) => ['id' => $row->id, 'identifier' => $row->identifier, 'title' => $row->title, 'type' => 'epic', 'spentMinutes' => (int) $row->spent, 'estimateMinutes' => (int) $row->estimate])->all();
    }

    /**
     * 當日有效工時分頁，欄位與單卡工時投影一致。
     *
     * @param  Project  $project  已授權專案
     * @param  ProjectTimeQueryData  $range  單日條件與分頁
     * @param  string  $author  限定作者User ID
     * @return LengthAwarePaginator 穩定倒序分頁
     */
    public function day(Project $project, ProjectTimeQueryData $range, string $author): LengthAwarePaginator
    {
        return $this->entries($project, $range, $author)
            ->join('issues as target', fn ($join) => $join->on('target.id', '=', 'entry.issue_id')->on('target.project_id', '=', 'entry.project_id'))
            ->join('users', 'users.id', '=', 'author.user_id')->join('projects as source_project', 'source_project.id', '=', 'entry.project_id')
            ->orderByDesc('entry.created_at')->orderByDesc('entry.id')
            ->paginate($range->page->perPage, ['entry.public_id as id', 'source_project.public_id as projectId', 'target.public_id as issueId', 'target.identifier as issueIdentifier', 'target.title as issueTitle', 'target.type as issueType', 'users.id as userId', 'users.name as userName', 'entry.work_date as workDate', 'entry.minutes', 'entry.note', 'entry.revision', 'entry.created_at', 'entry.updated_at'], 'page', $range->page->page);
    }
}
