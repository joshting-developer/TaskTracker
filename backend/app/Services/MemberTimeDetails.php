<?php

namespace App\Services;

use App\Domain\DomainError;
use App\Domain\Fields;

/** 將已授權的單人成員工時分組，限制明細與展開紀錄的回傳量。 */
class MemberTimeDetails
{
    private const PER_PAGE = 20;

    /**
     * 只投影目前範圍的有效紀錄，不回傳作者歷史或其他卡片內容。
     *
     * @param  array  $input  分組、頁碼與可選展開群組
     * @param  array  $entries  已由WorkspaceTimeReport篩選及授權的單人工時
     * @param  array  $issues  同工作空間的卡片索引
     * @param  array  $projects  已授權專案索引
     * @return array 分頁群組與可選分頁原始紀錄
     */
    public function get(array $input, array $entries, array $issues, array $projects): array
    {
        $groupBy = Fields::choice($input['detailGroup'], ['dates', 'issues'], '明細分組');
        $page = Fields::integer($input['detailPage'] ?? 1, '明細頁碼', 1, 1000000);
        $entryPage = Fields::integer($input['entryPage'] ?? 1, '紀錄頁碼', 1, 1000000);
        $expandedKey = Fields::text($input['entryGroupKey'] ?? '', '展開群組', 220);
        $groups = [];
        $expandedEntries = [];
        $dayMinutes = [];
        foreach ($entries as $entry) {
            $issue = $issues[$entry['issueId']] ?? null;
            if (! $issue || $issue['projectId'] !== $entry['projectId']) {
                continue;
            }
            $key = $groupBy === 'dates' ? $entry['workDate'].'|'.$entry['issueId'] : $entry['issueId'];
            $groups[$key] ??= [
                'key' => $key,
                'date' => $groupBy === 'dates' ? $entry['workDate'] : null,
                'issueId' => $issue['id'],
                'identifier' => $issue['identifier'],
                'title' => $issue['title'],
                'projectId' => $entry['projectId'],
                'projectName' => $projects[$entry['projectId']]['name'],
                'minutes' => 0,
                'entryCount' => 0,
            ];
            $groups[$key]['minutes'] += $entry['minutes'];
            $groups[$key]['entryCount']++;
            $dayMinutes[$entry['workDate']] = ($dayMinutes[$entry['workDate']] ?? 0) + $entry['minutes'];
            if ($key === $expandedKey) {
                $expandedEntries[] = [
                    'id' => $entry['id'],
                    'workDate' => $entry['workDate'],
                    'minutes' => $entry['minutes'],
                    'note' => $entry['note'] ?? '',
                ];
            }
        }
        if ($expandedKey !== '' && ! isset($groups[$expandedKey])) {
            DomainError::fail('NOT_FOUND', '找不到可查看的工時群組');
        }
        $rows = array_values($groups);
        foreach ($rows as &$row) {
            $row['dayMinutes'] = $row['date'] !== null ? $dayMinutes[$row['date']] : null;
        }
        unset($row);
        usort($rows, $groupBy === 'dates'
            ? fn ($a, $b) => strcmp($b['date'], $a['date']) ?: strcmp($a['identifier'], $b['identifier']) ?: strcmp($a['key'], $b['key'])
            : fn ($a, $b) => $b['minutes'] <=> $a['minutes'] ?: strcmp($a['key'], $b['key']));
        usort($expandedEntries, fn ($a, $b) => strcmp($b['workDate'], $a['workDate']) ?: strcmp($a['id'], $b['id']));
        $result = $this->paginate($rows, $page);
        $result['groupBy'] = $groupBy;
        $result['expanded'] = $expandedKey === '' ? null : [
            'key' => $expandedKey,
            ...$this->paginate($expandedEntries, $entryPage),
        ];

        return $result;
    }

    /**
     * 固定每頁20筆，資料減少後將頁碼收斂到最後一頁。
     *
     * @param  array  $rows  已穩定排序的資料
     * @param  int  $page  要求的頁碼
     * @return array 本頁資料與完整筆數
     */
    private function paginate(array $rows, int $page): array
    {
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $lastPage);

        return [
            'rows' => array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => $total,
            'lastPage' => $lastPage,
        ];
    }
}
