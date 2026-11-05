<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Domain\RecordRevision;
use App\Repositories\RecordStore;
use Illuminate\Support\Str;

/**
 * 管理本人工時、每日上限與作廢歷史。
 */
class TimeEntryService
{
    private ProjectAccess $access;

    /**
     * 設定此服務使用的資料入口與可信任相依。
     *
     * @param  RecordStore  $store  限定工作空間的資料存取入口
     * @param  array  $workspaceSession  從後端Session重新驗證的工作空間身分與權限
     * @return void
     */
    public function __construct(private RecordStore $store, private array $workspaceSession)
    {
        $this->access = new ProjectAccess($store, $workspaceSession);
    }

    /**
     * 取得目前UTC ISO時間供紀錄與歷史使用。
     *
     * @return string
     */
    private function now(): string
    {
        return now()->utc()->toISOString();
    }

    /**
     * 產生不依賴用戶輸入的紀錄識別碼。
     *
     * @return string
     */
    private function id(): string
    {
        return (string) Str::uuid();
    }

    /**
     * 驗證本人紀錄、工作日期與每日上限後保存工時。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @param  ?array  $current  目前版本的原始紀錄
     * @return array
     */
    private function saveTime(array $input, ?array $current = null): array
    {
        $userId = $this->workspaceSession['user']['id'];
        if ($current && ($current['userId'] !== $userId || ! empty($current['voidedAt']))) {
            DomainError::fail('FORBIDDEN', '只能修改自己的有效工時');
        }
        $issue = $this->store->require('issues', $current['issueId'] ?? $input['issueId'] ?? '');
        $date = Fields::date($input['workDate'] ?? null);
        if ($date > now('Asia/Taipei')->toDateString()) {
            DomainError::fail('INVALID_INPUT', '不能填報未來日期的工時');
        }
        $minutes = Fields::integer($input['minutes'] ?? null, '工時分鐘', 1, 1440);
        $otherDailyEntries = array_filter($this->store->all('timeEntries'), function ($entry) use ($userId, $date, $current) {
            $isEffective = empty($entry['voidedAt']);
            $isSameUserAndDate = $entry['userId'] === $userId && $entry['workDate'] === $date;
            $isOtherEntry = $entry['id'] !== ($current['id'] ?? '');

            return $isEffective && $isSameUserAndDate && $isOtherEntry;
        });
        $daily = array_sum(array_column($otherDailyEntries, 'minutes'));
        if ($daily + $minutes > 1440) {
            DomainError::fail('INVALID_INPUT', '同一人同一天的工時不能超過 24 小時');
        }
        $note = Fields::text($input['note'] ?? '', '工時說明', 1000);
        $baseEntry = $current ?? [
            'id' => $this->id(),
            'issueId' => $issue['id'],
            'projectId' => $issue['projectId'],
            'userId' => $userId,
            'createdAt' => $this->now(),
            'history' => [],
        ];
        $entry = [
            ...$baseEntry,
            'workDate' => $date,
            'minutes' => $minutes,
            'note' => $note,
            'revision' => ($current['revision'] ?? 0) + 1,
            'updatedAt' => $this->now(),
        ];
        $entry['history'][] = [
            'actor' => $this->workspaceSession['user']['name'],
            'at' => $this->now(),
            'before' => $current ? array_intersect_key($current, array_flip(['workDate', 'minutes', 'note'])) : null,
            'after' => [
                'workDate' => $date,
                'minutes' => $minutes,
                'note' => $note,
            ],
        ];
        $this->store->put('timeEntries', $entry['id'], $entry);

        return $entry;
    }

    /**
     * 建立本人的工時紀錄。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function logTime(array $input): array
    {
        return $this->saveTime($input);
    }

    /**
     * 確認原版本後修改本人的有效工時。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function updateTime(array $input): array
    {
        $entry = $this->store->require('timeEntries', $input['id'] ?? '');
        RecordRevision::assertCurrent($entry, $input, '工時');

        return $this->saveTime($input, $entry);
    }

    /**
     * 驗證作者與版本後作廢工時，保留原始紀錄。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function voidTime(array $input): array
    {
        $entry = $this->store->require('timeEntries', $input['id'] ?? '');
        if ($entry['userId'] !== $this->workspaceSession['user']['id']) {
            DomainError::fail('FORBIDDEN', '只能作廢自己的工時');
        }
        RecordRevision::assertCurrent($entry, $input, '工時');
        if (! empty($entry['voidedAt'])) {
            return $entry;
        }
        $next = [
            ...$entry,
            'voidedAt' => $this->now(),
            'updatedAt' => $this->now(),
            'revision' => $entry['revision'] + 1,
            'history' => [
                ...$entry['history'],
                [
                    'actor' => $this->workspaceSession['user']['name'],
                    'at' => $this->now(),
                    'kind' => 'voided',
                ],
            ],
        ];
        $this->store->put('timeEntries', $next['id'], $next);

        return $next;
    }
}
