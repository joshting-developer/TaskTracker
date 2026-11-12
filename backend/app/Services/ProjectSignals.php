<?php

namespace App\Services;

use App\Events\ProjectChanged;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** 將已驗證寫入轉為commit後的Queue事件，不使廣播失敗改寫成功交易。 */
class ProjectSignals
{
    /** 保存可信Session的空間範圍。
     * @param  array  $context  後端重新取得的身分
     * @return void
     */
    public function __construct(private array $context) {}

    /** 在交易commit後才派送；rollback不執行，Redis故障只記受控log。
     * @param  string  $projectId  已完成寫入的專案ID
     * @return void
     */
    public function changed(string $projectId): void
    {
        if ($projectId === '' || config('broadcasting.default') !== 'reverb') {
            return;
        }
        $workspaceId = $this->context['workspace']['id'];
        DB::afterCommit(function () use ($workspaceId, $projectId) {
            try {
                event(new ProjectChanged($workspaceId, $projectId));
            } catch (Throwable $error) {
                Log::warning('Project realtime dispatch failed; polling remains available', ['exception' => $error::class]);
            }
        });
    }

    /** 空間設定或角色異動讓既有專案訂閱重新查資格；不推成員資料。
     * @return void
     */
    public function workspaceChanged(): void
    {
        if (config('broadcasting.default') !== 'reverb') {
            return;
        }
        $projects = Project::whereHas('workspace', fn ($query) => $query->where('public_id', $this->context['workspace']['id']))
            ->orderBy('id')->select('public_id')->cursor();
        foreach ($projects as $project) {
            $this->changed($project->public_id);
        }
    }
}
