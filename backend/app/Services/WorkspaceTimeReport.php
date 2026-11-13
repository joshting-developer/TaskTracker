<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspacePermissions;
use App\Data\PageQueryData;
use App\Data\TimeReportData;
use App\Data\WorkspaceContext;
use App\Domain\DomainError;
use App\Repositories\TimeReportRepository;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;

/** 彙整工作空間內有權讀取的有效工時，不重複累計Epic彙總。 */
class WorkspaceTimeReport
{
    /**
     * 注入無狀態工時資料入口, 當次租戶資格由方法傳入
     *
     * @param  TimeReportRepository  $reports  有租戶／期間範圍的資料查詢
     */
    public function __construct(private TimeReportRepository $reports) {}

    /**
     * 只讀統計或指定成員的明細
     *
     * @param  WorkspaceContext  $context  交易內重驗的身分
     * @param  TimeReportData  $filters  已驗證期間與範圍
     * @param  bool  $details  是否只回明細
     * @return array 對應用途的最小回應
     */
    public function report(WorkspaceContext $context, TimeReportData $filters, bool $details): array
    {
        if ($details !== ($filters->detailGroup !== null)) {
            DomainError::fail('INVALID_INPUT', '請使用對應的分析或成員明細介面');
        }

        return $this->get($context->legacyIdentity(), $filters, false, $details);
    }

    /**
     * 依工作日期與可選成員／專案範圍回傳統計，按需查單人成員明細。
     *
     * @param  array  $workspaceSession  交易內重驗的租戶資格
     * @param  array|TimeReportData  $input  HTTP DTO或既有非HTTP條件
     * @param  bool  $includeOptions  僅舊RPC附帶第一頁相容選項
     * @param  bool  $detailOnly  只回明細與總計
     * @return array 分鐘整數、成員／專案分布、含零日期序列及可選分頁明細
     */
    public function get(array $workspaceSession, array|TimeReportData $input, bool $includeOptions = true, bool $detailOnly = false): array
    {
        WorkspacePermissions::authorize($workspaceSession, 'workspace.read');
        WorkspacePermissions::authorize($workspaceSession, 'workspace.timeReports.read');
        $filters = $input instanceof TimeReportData ? $input : TimeReportData::from($input);
        $start = $filters->startDate;
        $end = $filters->endDate;
        $detailGroup = $filters->detailGroup;
        $allowed = $this->projectScope($workspaceSession, $detailGroup !== null);
        $projectId = $filters->projectId;
        $userId = $filters->userId;
        if ($projectId !== '' && ! $this->reports->projectExists($allowed, $projectId)) {
            DomainError::fail('NOT_FOUND', '找不到可分析的專案');
        }
        if ($userId !== '' && ! $this->reports->memberExists($workspaceSession['workspace']['id'], $userId)) {
            DomainError::fail('NOT_FOUND', '找不到可分析的成員');
        }
        $memberRows = [];
        $projectRows = [];
        $allocations = [];
        $days = [];
        for ($date = new DateTimeImmutable($start); $date->format('Y-m-d') <= $end; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $days[$key] = [
                'date' => $key,
                'minutes' => 0,
                'entryCount' => 0,
            ];
        }
        $totalMinutes = 0;
        $entryCount = 0;
        $aggregate = $this->reports->summary($workspaceSession['workspace']['id'], clone $allowed, $filters, ! $detailOnly);
        foreach ($aggregate['allocations'] as $entry) {
            $member = $entry['userId'];
            $project = $entry['projectId'];
            $memberRows[$member] ??= [
                'id' => $member, 'name' => $entry['memberName'],
                'minutes' => 0, 'entryCount' => 0, 'projectIds' => [],
            ];
            $projectRows[$project] ??= [
                'id' => $project, 'name' => $entry['projectName'], 'code' => $entry['projectCode'],
                'minutes' => 0, 'entryCount' => 0, 'memberIds' => [],
            ];
            $minutes = $entry['minutes'];
            $count = $entry['entryCount'];
            $allocations[] = [
                'userId' => $member, 'memberName' => $entry['memberName'],
                'projectId' => $project, 'projectName' => $entry['projectName'], 'minutes' => $minutes,
            ];
            $memberRows[$member]['minutes'] += $minutes;
            $memberRows[$member]['entryCount'] += $count;
            $memberRows[$member]['projectIds'][$project] = true;
            $projectRows[$project]['minutes'] += $minutes;
            $projectRows[$project]['entryCount'] += $count;
            $projectRows[$project]['memberIds'][$member] = true;
            $totalMinutes += $minutes;
            $entryCount += $count;
        }
        foreach ($aggregate['days'] as $day) {
            $days[$day['date']] = $day;
            if ($detailOnly) {
                $totalMinutes += $day['minutes'];
                $entryCount += $day['entryCount'];
            }
        }
        $memberRows = array_values(array_map(function ($row) {
            $row['projectCount'] = count($row['projectIds']);
            unset($row['projectIds']);

            return $row;
        }, $memberRows));
        $projectRows = array_values(array_map(function ($row) {
            $row['memberCount'] = count($row['memberIds']);
            unset($row['memberIds']);

            return $row;
        }, $projectRows));
        usort($memberRows, fn ($a, $b) => $b['minutes'] <=> $a['minutes'] ?: strcmp($a['id'], $b['id']));
        usort($projectRows, fn ($a, $b) => $b['minutes'] <=> $a['minutes'] ?: strcmp($a['id'], $b['id']));

        $result = [
            'range' => [
                'startDate' => $start,
                'endDate' => $end,
                'timezone' => 'Asia/Taipei',
                'projectId' => $projectId,
                'userId' => $userId,
            ],
            'summary' => [
                'totalMinutes' => $totalMinutes,
                'entryCount' => $entryCount,
                'activeDays' => count(array_filter($days, fn ($day) => $day['minutes'] > 0)),
            ],
            'members' => $memberRows,
            'projects' => $projectRows,
            'days' => array_values($days),
            'allocations' => array_values($allocations),

        ];
        if ($detailGroup !== null) {
            $result['details'] = $this->reports->details(
                $workspaceSession['workspace']['id'], clone $allowed, $filters, $days,
            );
        }

        if ($detailOnly) {
            unset($result['members'], $result['projects'], $result['days'], $result['allocations']);
        }
        if ($includeOptions) {
            $result['options'] = [
                'projects' => $this->options($workspaceSession, 'projects', new PageQueryData(1, 100))->items(),
                'members' => $this->options($workspaceSession, 'members', new PageQueryData(1, 100))->items(),
            ];
        }

        return $result;
    }

    /**
     * 以有效permission求必要Project角色交集, 不以角色名稱直接放行
     *
     * @param  array  $workspaceSession  交易內重驗的租戶資格
     * @param  bool  $details  明細另需issue.read
     * @return Builder 只讀取可分析專案ID的SQL子查詢
     */
    private function projectScope(array $workspaceSession, bool $details): Builder
    {
        $required = ['project.read', 'timeEntry.readTeam', ...($details ? ['issue.read'] : [])];
        $missing = array_values(array_filter($required, fn ($permission) => ! in_array(ProjectAccess::ANY[$permission], $workspaceSession['permissions'], true)));
        $roles = array_column(array_filter(ProjectAccess::roles(), fn ($role) => array_diff($missing, $role['permissions']) === []), 'id');

        return $this->reports->authorizedProjects($workspaceSession['workspace']['id'], $workspaceSession['user']['id'], $missing === [], $roles);
    }

    /**
     * 選項另行搜尋分頁, 不在分析結果夾帶整個成員目錄
     *
     * @param  array  $workspaceSession  交易內重驗的租戶資格
     * @param  string  $kind  projects或members
     * @param  PageQueryData  $page  有界頁面條件
     * @return LengthAwarePaginator 最小選項
     */
    public function options(array $workspaceSession, string $kind, PageQueryData $page): LengthAwarePaginator
    {
        WorkspacePermissions::authorize($workspaceSession, 'workspace.read');
        WorkspacePermissions::authorize($workspaceSession, 'workspace.timeReports.read');

        return $this->reports->options($workspaceSession['workspace']['id'], $this->projectScope($workspaceSession, false), $kind, $page);
    }
}
