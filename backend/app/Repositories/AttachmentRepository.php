<?php

namespace App\Repositories;

use App\Database\DatabaseTimes;
use App\Models\Attachment;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** 附件metadata與配額的限定查詢, 不決定角色或操作流程 */
class AttachmentRepository
{
    /** 同Project取單筆metadata
     * @param  Project  $project  已授權專案
     * @param  string  $id  附件公開ID
     * @return ?Attachment 單筆
     */
    public function find(Project $project, string $id): ?Attachment
    {
        return $this->query($project)->where('public_id', $id)->first();
    }

    /** 同專案鎖定有界描述附件, 與清理器協調
     * @param  Project  $project  已授權專案
     * @param  list<string>  $ids  最多20個公開ID
     * @return array<string,Attachment> 以公開ID索引
     */
    public function forDescription(Project $project, array $ids): array
    {
        return $this->query($project)->whereIn('public_id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('public_id')->all();
    }

    /** 同Issue一次綁定已驗證的pending附件
     * @param  Project  $project  已授權專案
     * @param  Issue  $issue  同專案任務
     * @param  list<int>  $ids  已驗證的內部ID
     * @return void
     */
    public function bindDescription(Project $project, Issue $issue, array $ids): void
    {
        $this->query($project)->whereIn('id', $ids)->update(['issue_id' => $issue->id, 'status' => 'active', 'revision' => DB::raw('revision + 1'), 'expires_at' => null, 'updated_at' => now()]);
    }

    /** 同租戶上傳requestId查重, 跨Project重用也須比對簽章
     * @param  Project  $project  已授權專案
     * @param  string  $user  操作者
     * @param  string  $request  上傳請求ID
     * @return ?Attachment 首次保留結果
     */
    public function uploadRequest(Project $project, string $user, string $request): ?Attachment
    {
        return Attachment::where('workspace_id', $project->workspace_id)->where('uploaded_by', $user)->where('upload_request_id', $request)->first();
    }

    /** 保留物件metadata
     * @param  array  $attributes  Service選定欄位
     * @return Attachment 新記錄
     */
    public function create(array $attributes): Attachment
    {
        return Attachment::create($attributes);
    }

    /** 更新指定附件, 不讀取其他資源
     * @param  Attachment  $attachment  已授權記錄
     * @param  array  $attributes  選定異動
     * @return Attachment 更新實體
     */
    public function save(Attachment $attachment, array $attributes): Attachment
    {
        $attachment->fill($attributes)->save();

        return $attachment;
    }

    /** 配額包含尚未完成及刪除保留中的實體bytes
     * @param  Project  $project  已授權專案
     * @param  string  $user  操作者
     * @return array{bytes:int,pending:int,generalBytes:int} 工作區與本人用量
     */
    public function quota(Project $project, string $user): array
    {
        $row = Attachment::where('workspace_id', $project->workspace_id)->where('status', '!=', 'purged')
            ->selectRaw('COALESCE(SUM(byte_size), 0) as bytes')
            ->selectRaw("COALESCE(SUM(CASE WHEN mime_type NOT LIKE 'image/%' THEN byte_size ELSE 0 END), 0) as general_bytes")
            ->selectRaw("SUM(CASE WHEN uploaded_by = ? AND status IN ('uploading', 'pending') THEN 1 ELSE 0 END) as pending", [$user])->first();

        return ['bytes' => (int) $row->bytes, 'pending' => (int) $row->pending, 'generalBytes' => (int) $row->general_bytes];
    }

    /** 指定Issue的有效附件分頁
     * @param  Project  $project  已授權專案
     * @param  Issue  $issue  同專案任務
     * @param  int  $page  頁碼
     * @return LengthAwarePaginator 最多20筆
     */
    public function page(Project $project, Issue $issue, int $page): LengthAwarePaginator
    {
        return $this->query($project)->where('issue_id', $issue->id)->where('status', 'active')->orderBy('id')->paginate(20, ['*'], 'page', $page);
    }

    /** 同Issue有效附件數
     * @param  Project  $project  已授權專案
     * @param  Issue  $issue  同專案任務
     * @param  bool  $generalOnly  只計PDF／ZIP
     * @return int 件數
     */
    public function count(Project $project, Issue $issue, bool $generalOnly = false): int
    {
        return $this->query($project)->where('issue_id', $issue->id)->where('status', 'active')->when($generalOnly, fn ($query) => $query->where('mime_type', 'not like', 'image/%'))->count();
    }

    /** 取Issue版本最小欄位
     * @param  Project  $project  已授權專案
     * @param  string  $id  Issue公開ID
     * @return ?Issue 可驗版本的單筆
     */
    public function issue(Project $project, string $id): ?Issue
    {
        return Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->where('public_id', $id)->first(['id', 'public_id', 'workspace_id', 'project_id', 'revision', 'identifier']);
    }

    /** 同交易變更Issue版本並追加附件異動歷史
     * @param  Issue  $issue  已驗版本的任務
     * @param  array  $event  Service產生事件
     * @return void
     */
    public function touchIssue(Issue $issue, array $event): void
    {
        DB::table('issues')->where('id', $issue->id)->where('workspace_id', $issue->workspace_id)->update(['revision' => DB::raw('revision + 1'), 'updated_at' => DatabaseTimes::store($event['at'])]);
        $last = DB::table('audit_events')->where('workspace_id', $issue->workspace_id)->where('entity_type', 'issues')->where('entity_id', $issue->id)->where('stream', 'history')->orderByDesc('position')->value('position');
        DB::table('audit_events')->insert(['workspace_id' => $issue->workspace_id, 'entity_type' => 'issues', 'entity_id' => $issue->id, 'stream' => 'history', 'position' => $last === null ? 0 : $last + 1, 'event_data' => json_encode($event, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }

    /** 清理器只拿有界過期候選ID, 每次仍須交易重驗
     * @param  int  $after  上次內部ID
     * @return array<object> 最多100筆
     */
    public function expired(int $after): array
    {
        return Attachment::where('id', '>', $after)->whereIn('status', ['uploading', 'pending', 'deleted', 'purging'])->where('expires_at', '<=', now())->orderBy('id')->limit(100)->get(['id', 'workspace_id', 'public_id', 'storage_disk', 'storage_key'])->all();
    }

    /** 清理時重讀並鎖住, 防止與bind競爭
     * @param  int  $id  內部ID
     * @return ?Attachment 仍過期的記錄
     */
    public function expiredLocked(int $id): ?Attachment
    {
        return Attachment::where('id', $id)->whereIn('status', ['uploading', 'pending', 'deleted', 'purging'])->where('expires_at', '<=', now())->lockForUpdate()->first();
    }

    /** 由已限定附件解析Issue公開ID
     * @param  Project  $project  已授權專案
     * @param  ?int  $id  任務內部ID
     * @return ?string 同專案公開ID
     */
    public function issuePublicId(Project $project, ?int $id): ?string
    {
        return Issue::where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->where('id', $id)->value('public_id');
    }

    /** 同Project資料入口
     * @param  Project  $project  可信專案
     * @return Builder 限定查詢, 不流出Repository
     */
    private function query(Project $project): Builder
    {
        return Attachment::where('workspace_id', $project->workspace_id)->where('project_id', $project->id);
    }
}
