<?php

namespace App\Services;

use App\Data\ScheduleQueryData;
use App\Data\WorkspaceContext;
use App\Repositories\ScheduleRepository;

/** 協調時程查詢的Project資格, 每次讀取重新驗權。 */
class ScheduleService
{
    /**
     * 注入Project資格與時程投影。
     *
     * @param  ProjectReadService  $projects  專案資格服務
     * @param  ScheduleRepository  $schedule  有界時程查詢
     */
    public function __construct(private ProjectReadService $projects, private ScheduleRepository $schedule) {}

    /**
     * 有issue.read才可讀, 回傳當頁卡片與同條件的未排程數。
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分與工作區
     * @param  string  $projectId  Project公開ID
     * @param  ScheduleQueryData  $filters  有界區間與分頁
     * @return array{page:\Illuminate\Contracts\Pagination\LengthAwarePaginator,unscheduled:int} 時程結果
     */
    public function index(WorkspaceContext $context, string $projectId, ScheduleQueryData $filters): array
    {
        $project = $this->projects->authorized($context, $projectId, 'issue.read')->project;

        return ['page' => $this->schedule->page($project, $filters), 'unscheduled' => $this->schedule->unscheduled($project, $filters)];
    }
}
