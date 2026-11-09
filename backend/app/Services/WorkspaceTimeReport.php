<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspacePermissions;
use App\Data\TimeReportData;
use App\Domain\DomainError;
use App\Repositories\RecordStore;
use App\Repositories\TimeReportRepository;
use DateTimeImmutable;

/** 彙整工作空間內有權讀取的有效工時，不重複累計Epic彙總。 */
class WorkspaceTimeReport
{
    /**
     * 接收已由Session驗證的租戶資料入口與操作者資格。
     *
     * @param  RecordStore  $store  目前工作空間的儲存入口
     * @param  array  $workspaceSession  後端重新驗證的資格
     * @param  TimeReportRepository  $reports  有租戶／期間範圍的資料查詢
     */
    public function __construct(private RecordStore $store, private array $workspaceSession, private TimeReportRepository $reports) {}

    /**
     * 依工作日期與可選成員／專案範圍回傳統計，按需查單人成員明細。
     *
     * @param  array|TimeReportData  $input  HTTP DTO或既有非HTTP條件
     * @return array 分鐘整數、成員／專案分布、含零日期序列及可選分頁明細
     */
    public function get(array|TimeReportData $input): array
    {
        WorkspacePermissions::authorize($this->workspaceSession, 'workspace.read');
        WorkspacePermissions::authorize($this->workspaceSession, 'workspace.timeReports.read');
        $filters = $input instanceof TimeReportData ? $input : TimeReportData::from($input);
        $start = $filters->startDate;
        $end = $filters->endDate;
        $detailGroup = $filters->detailGroup;
        $allProjects = $this->store->all('projects');
        $allMembers = $this->store->all('members');
        $access = ProjectAccess::forRead($this->store, $this->workspaceSession, $allProjects, $allMembers);
        $projects = [];
        foreach ($allProjects as $project) {
            $canReadDetails = $detailGroup === null || $access->can($project['id'], 'issue.read');
            if ($access->can($project['id'], 'project.read') && $access->can($project['id'], 'timeEntry.readTeam') && $canReadDetails) {
                $projects[$project['id']] = $project;
            }
        }
        $members = array_column($allMembers, null, 'id');
        $projectId = $filters->projectId;
        $userId = $filters->userId;
        if ($projectId !== '' && ! isset($projects[$projectId])) {
            DomainError::fail('NOT_FOUND', '找不到可分析的專案');
        }
        if ($userId !== '' && ! isset($members[$userId])) {
            DomainError::fail('NOT_FOUND', '找不到可分析的成員');
        }
        $detailEntries = [];
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
        $entries = $this->reports->entries($this->workspaceSession['workspace']['id'], array_keys($projects), $filters);
        foreach ($entries as $row) {
            $entry = $row->entry();
            if ($detailGroup !== null) {
                $detailEntries[] = $entry;
            }
            $member = $entry['userId'];
            $project = $entry['projectId'];
            $memberRows[$member] ??= [
                'id' => $member,
                'name' => $members[$member]['name'] ?? '歷史成員',
                'minutes' => 0,
                'entryCount' => 0,
                'projectIds' => [],
            ];
            $projectRows[$project] ??= [
                'id' => $project,
                'name' => $projects[$project]['name'],
                'code' => $projects[$project]['code'],
                'minutes' => 0,
                'entryCount' => 0,
                'memberIds' => [],
            ];
            $minutes = $entry['minutes'];
            $allocationKey = $member.':'.$project;
            $allocations[$allocationKey] ??= [
                'userId' => $member,
                'memberName' => $members[$member]['name'] ?? '歷史成員',
                'projectId' => $project,
                'projectName' => $projects[$project]['name'],
                'minutes' => 0,
            ];
            $allocations[$allocationKey]['minutes'] += $minutes;
            $memberRows[$member]['minutes'] += $minutes;
            $memberRows[$member]['entryCount']++;
            $memberRows[$member]['projectIds'][$project] = true;
            $projectRows[$project]['minutes'] += $minutes;
            $projectRows[$project]['entryCount']++;
            $projectRows[$project]['memberIds'][$member] = true;
            $days[$entry['workDate']]['minutes'] += $minutes;
            $days[$entry['workDate']]['entryCount']++;
            $totalMinutes += $minutes;
            $entryCount++;
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
            'options' => [
                'projects' => array_values(array_map(fn ($project) => [
                    'id' => $project['id'],
                    'name' => $project['name'],
                ], $projects)),
                'members' => array_values(array_map(fn ($member) => [
                    'id' => $member['id'],
                    'name' => $member['name'],
                ], $members)),
            ],
        ];
        if ($detailGroup !== null) {
            $result['details'] = (new MemberTimeDetails)->get(
                $filters->detailInput(),
                $detailEntries,
                $entries->mapWithKeys(fn ($row) => [$row->issueId => [
                    'id' => $row->issueId, 'projectId' => $row->projectId,
                    'identifier' => $row->identifier, 'title' => $row->title,
                ]])->all(),
                $projects,
            );
        }

        return $result;
    }
}
