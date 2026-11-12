<?php

namespace App\Services;

use App\Data\ProjectTimeQueryData;
use App\Domain\DomainError;
use App\Models\Project;
use App\Repositories\ProjectTimeReadRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** 專案工時月曆：沿用團隊／本人工時資格，不擴張成跨專案報表（見工時分析）。 */
class ProjectTimeReadService
{
    /**
     * 注入專案授權與工時聚合入口。
     *
     * @param  ProjectReadService  $projects  專案資格服務
     * @param  ProjectTimeReadRepository  $entries  有界工時查詢
     */
    public function __construct(private ProjectReadService $projects, private ProjectTimeReadRepository $entries) {}

    /**
     * 解析當次可讀作者：無團隊資格只能看本人，有團隊資格才可指定成員。
     *
     * @param  array<string,mixed>  $actor  可信操作者
     * @param  string  $projectId  專案公開ID
     * @param  ProjectTimeQueryData  $range  查詢條件
     * @return array{0:Project,1:string,2:bool} 專案、限定作者及是否團隊範圍
     */
    private function scope(array $actor, string $projectId, ProjectTimeQueryData $range): array
    {
        $access = $this->projects->authorized($actor, $projectId, 'issue.read');
        $team = in_array('timeEntry.readTeam', $access->permissions, true);
        if (! $team && ! in_array('timeEntry.readOwn', $access->permissions, true)) {
            DomainError::fail('FORBIDDEN', '沒有工時讀取權限');
        }

        return [$access->project, $team ? $range->member : $actor['user']['id'], $team];
    }

    /**
     * 整月SQL摘要，回傳本次實際套用的範圍供前端顯示。
     *
     * @param  array<string,mixed>  $actor  可信操作者
     * @param  string  $projectId  專案公開ID
     * @param  ProjectTimeQueryData  $range  整月條件
     * @return array<string,mixed> 月曆摘要
     */
    public function calendar(array $actor, string $projectId, ProjectTimeQueryData $range): array
    {
        [$project, $author, $team] = $this->scope($actor, $projectId, $range);

        return ['month' => substr($range->from, 0, 7), 'member' => $author, 'team' => $team, 'limit' => ProjectTimeReadRepository::SUMMARY_LIMIT,
            ...$this->entries->calendar($project, $range, $author)];
    }

    /**
     * 點選日期後才讀取的當日明細分頁。
     *
     * @param  array<string,mixed>  $actor  可信操作者
     * @param  string  $projectId  專案公開ID
     * @param  ProjectTimeQueryData  $range  單日條件
     * @return LengthAwarePaginator 當日有效工時
     */
    public function day(array $actor, string $projectId, ProjectTimeQueryData $range): LengthAwarePaginator
    {
        [$project, $author] = $this->scope($actor, $projectId, $range);

        return $this->entries->day($project, $range, $author);
    }
}
