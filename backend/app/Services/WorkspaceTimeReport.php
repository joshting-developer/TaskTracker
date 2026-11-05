<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspacePermissions;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Repositories\RecordStore;
use DateTimeImmutable;

/** 彙整工作空間內有權讀取的有效工時，不重複累計Epic彙總。 */
class WorkspaceTimeReport
{
    /**
     * 接收已由Session驗證的租戶資料入口與操作者資格。
     *
     * @param  RecordStore  $store  目前工作空間的儲存入口
     * @param  array  $workspaceSession  後端重新驗證的資格
     */
    public function __construct(private RecordStore $store, private array $workspaceSession) {}

    /**
     * 依工作日期與可選成員／專案範圍回傳三種一致的統計。
     *
     * @param  array  $input  起訖日與可選篩選ID
     * @return array 分鐘整數、成員／專案分布及含零日期序列
     */
    public function get(array $input): array
    {
        WorkspacePermissions::authorize($this->workspaceSession, 'workspace.timeReports.read');
        [$start, $end] = $this->dateRange($input);
        $access = new ProjectAccess($this->store, $this->workspaceSession);
        $projects = [];
        foreach ($this->store->all('projects') as $project) {
            if ($access->can($project['id'], 'project.read') && $access->can($project['id'], 'timeEntry.readTeam')) {
                $projects[$project['id']] = $project;
            }
        }
        $members = array_column($this->store->all('members'), null, 'id');
        $projectId = Fields::text($input['projectId'] ?? '', '專案', 100);
        $userId = Fields::text($input['userId'] ?? '', '成員', 100);
        if ($projectId !== '' && ! isset($projects[$projectId])) {
            DomainError::fail('NOT_FOUND', '找不到可分析的專案');
        }
        if ($userId !== '' && ! isset($members[$userId])) {
            DomainError::fail('NOT_FOUND', '找不到可分析的成員');
        }
        $memberRows = [];
        $projectRows = [];
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
        foreach ($this->store->all('timeEntries') as $entry) {
            if (! empty($entry['voidedAt']) || ! isset($projects[$entry['projectId']])) {
                continue;
            }
            if ($entry['workDate'] < $start || $entry['workDate'] > $end) {
                continue;
            }
            if ($projectId !== '' && $entry['projectId'] !== $projectId || $userId !== '' && $entry['userId'] !== $userId) {
                continue;
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

        return [
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
    }

    /**
     * 驗證真實日曆日期、先後及366日範圍，限制日期序列長度。
     *
     * @param  array  $input  使用者指定日期
     * @return array 含首尾的起訖日期
     */
    private function dateRange(array $input): array
    {
        $dates = [];
        foreach (['startDate', 'endDate'] as $field) {
            $value = Fields::text($input[$field] ?? '', '分析日期', 10, true);
            if (! preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $value)) {
                DomainError::fail('INVALID_INPUT', '請填寫有效的分析日期');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (! $date || $date->format('Y-m-d') !== $value) {
                DomainError::fail('INVALID_INPUT', '請填寫有效的分析日期');
            }
            $dates[] = $value;
        }
        [$start, $end] = $dates;
        if ($start > $end || (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days > 365) {
            DomainError::fail('INVALID_INPUT', '起訖日期需依序且範圍最多366天');
        }

        return $dates;
    }
}
