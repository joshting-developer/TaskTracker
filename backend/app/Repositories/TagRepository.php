<?php

namespace App\Repositories;

use App\Data\PageQueryData;
use App\Database\DatabaseTimes;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectTag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** 標籤資料只在已授權專案內讀寫, 不載整個工作區 */
class TagRepository
{
    /** 限定資料庫租戶與專案
     * @param  Project  $project  已授權專案
     * @return Builder<ProjectTag> 尚未執行的查詢
     */
    private function query(Project $project): Builder
    {
        return ProjectTag::where('workspace_id', $project->workspace_id)->where('project_id', $project->id);
    }

    /** 依名稱與 ID 穩定分頁, 字元搜尋不解釋萬用字元
     * @param  Project  $project  已授權專案
     * @param  PageQueryData  $page  有界分頁
     * @return LengthAwarePaginator<ProjectTag> 有效定義分頁
     */
    public function page(Project $project, PageQueryData $page): LengthAwarePaginator
    {
        $query = $this->query($project)->whereNull('deleted_at');
        if ($page->search !== '') {
            $needle = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $page->search);
            $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$needle.'%']);
        }

        return $query->orderBy('name')->orderBy('id')->paginate($page->perPage, ['id', 'public_id', 'name', 'color', 'description', 'revision', 'created_at', 'updated_at'], 'page', $page->page);
    }

    /** 以同專案公開 ID 取得定義, replay 授權可包含停用定義
     * @param  Project  $project  已授權專案
     * @param  string  $id  標籤公開 ID
     * @param  bool  $includeDeleted  是否為既有資源重播檢查
     * @return ?ProjectTag 單一定義
     */
    public function find(Project $project, string $id, bool $includeDeleted = false): ?ProjectTag
    {
        return $this->query($project)->when(! $includeDeleted, fn ($query) => $query->whereNull('deleted_at'))->where('public_id', $id)->first();
    }

    /** 同專案名稱去重, 不以名稱判斷存取權
     * @param  Project  $project  已授權專案
     * @param  string  $nameKey  正規化名稱
     * @param  ?int  $except  修改時排除自己
     * @return bool 是否重複
     */
    public function nameExists(Project $project, string $nameKey, ?int $except): bool
    {
        return $this->query($project)->where('name_key', $nameKey)->when($except !== null, fn ($q) => $q->where('id', '!=', $except))->exists();
    }

    /** 計算有效定義上限
     * @param  Project  $project  已授權專案
     * @return int 有效定義數
     */
    public function count(Project $project): int
    {
        return $this->query($project)->whereNull('deleted_at')->count();
    }

    /** 建立已驗證定義
     * @param  Project  $project  已授權專案
     * @param  array<string,mixed>  $data  經 Service 驗證的欄位
     * @return ProjectTag 新定義
     */
    public function create(Project $project, array $data): ProjectTag
    {
        return ProjectTag::create(['workspace_id' => $project->workspace_id, 'project_id' => $project->id, ...$data]);
    }

    /** 保存單一定義
     * @param  ProjectTag  $tag  同專案定義
     * @param  array<string,mixed>  $data  已驗證的變更
     * @return void
     */
    public function save(ProjectTag $tag, array $data): void
    {
        $tag->forceFill($data)->save();
    }

    /** 刪除單一定義的關聯, 單一 SQL 不載入或逐張修改卡片
     * @param  ProjectTag  $tag  已授權同專案定義
     * @return void
     */
    public function detachTag(ProjectTag $tag): void
    {
        DB::table('issue_tags')->where('workspace_id', $tag->workspace_id)->where('project_id', $tag->project_id)->where('tag_id', $tag->id)->delete();
    }

    /** 定義變更增加專案版本, 既有看板輕量檢查不增加查詢
     * @param  Project  $project  已授權專案
     * @return void
     */
    public function touchProject(Project $project): void
    {
        Project::where('workspace_id', $project->workspace_id)->whereKey($project->id)->increment('revision', 1, ['updated_at' => DatabaseTimes::store(now()->toISOString())]);
    }

    /** 取得單一同專案卡片及已套用標籤, 不讀描述或活動全集
     * @param  Project  $project  已授權專案
     * @param  string  $id  卡片公開 ID
     * @return ?Issue 單一卡片及有界標籤
     */
    public function issue(Project $project, string $id): ?Issue
    {
        return Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->where('public_id', $id)->with('tags')->first(['id', 'public_id', 'workspace_id', 'project_id', 'identifier', 'revision']);
    }

    /** 批次驗證少量有效標籤, 未找到的 ID 交 Service 拒絕
     * @param  Project  $project  已授權專案
     * @param  list<string>  $ids  至多 20 個公開 ID
     * @return Collection<int,ProjectTag> 同專案有效標籤
     */
    public function references(Project $project, array $ids): Collection
    {
        return $this->query($project)->whereNull('deleted_at')->whereIn('public_id', $ids)->get(['id', 'public_id', 'name', 'color']);
    }

    /** 套用已驗證差量並記錄單一卡片版本與歷史, 不逐張批改卡片
     * @param  Issue  $issue  同專案卡片
     * @param  Collection<int,ProjectTag>  $add  要新增的有效定義
     * @param  list<string>  $remove  要移除的公開 ID
     * @param  array<string,mixed>  $event  Service 產生的異動事件
     * @return void
     */
    public function apply(Issue $issue, Collection $add, array $remove, array $event): void
    {
        $base = DB::table('issue_tags')->where('workspace_id', $issue->workspace_id)->where('project_id', $issue->project_id)->where('issue_id', $issue->id);
        if ($remove !== []) {
            $base->whereIn('tag_id', DB::table('project_tags')->where('workspace_id', $issue->workspace_id)->where('project_id', $issue->project_id)->whereIn('public_id', $remove)->select('id'))->delete();
        }
        if ($add->isNotEmpty()) {
            DB::table('issue_tags')->insertOrIgnore($add->map(fn ($tag) => ['workspace_id' => $issue->workspace_id, 'project_id' => $issue->project_id, 'issue_id' => $issue->id, 'tag_id' => $tag->id])->all());
        }
        $issue->forceFill(['revision' => $issue->revision + 1, 'updated_at' => DatabaseTimes::store($event['at'])])->save();
        $history = DB::table('audit_events')->where('workspace_id', $issue->workspace_id)->where('entity_type', 'issues')->where('entity_id', $issue->id)->where('stream', 'history');
        $position = (int) ($history->max('position') ?? -1) + 1;
        DB::table('audit_events')->insert(['workspace_id' => $issue->workspace_id, 'entity_type' => 'issues', 'entity_id' => $issue->id, 'stream' => 'history', 'position' => $position, 'event_data' => json_encode($event, JSON_THROW_ON_ERROR)]);
    }
}
