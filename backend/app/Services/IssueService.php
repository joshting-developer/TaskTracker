<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Domain\RecordRevision;
use App\Repositories\RecordStore;
use Illuminate\Support\Str;

/**
 * 管理任務、父子與雙向關聯，維持同交易歷史和通知預覽。
 */
class IssueService
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
     * 建立任務並同步編號、關聯、看板收錄與指派通知。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function createIssue(array $input): array
    {
        $project = $this->store->require('projects', $input['projectId'] ?? '');
        $fields = Fields::issue($input, $project);
        $this->validateLinks($project, $fields);
        $issue = [
            'id' => $this->id(),
            'projectId' => $project['id'],
            'identifier' => $project['code'].'-'.$project['nextNumber'],
            ...$fields,
            'revision' => 1,
            'createdAt' => $this->now(),
            'updatedAt' => $this->now(),
            'comments' => [],
            'history' => [[
                'id' => $this->id(),
                'kind' => 'created',
                'actor' => $this->workspaceSession['user']['name'],
                'at' => $this->now(),
                'changes' => [],
            ]],
        ];
        $this->store->put('projects', $project['id'], [
            ...$project,
            'nextNumber' => $project['nextNumber'] + 1,
        ]);
        $this->store->put('issues', $issue['id'], $issue);
        $this->syncRelations($issue, []);
        if (! empty($input['boardId']) && $input['boardId'] !== 'default') {
            (new ProjectBoards($this->store, $this->workspaceSession))->includeCreatedIssue(Fields::text($input['boardId'], '看板', 100, true), $issue);
        }
        $this->notify($issue, '指派任務', $this->workspaceSession['user']['name'].' 將任務指派給你', $issue['assignee']);

        return $issue;
    }

    /**
     * 檢查父子層級、Epic、雙向關聯及負責人的有效性。
     *
     * @param  array  $project  目前專案內容與設定
     * @param  array  $fields  已選取或驗證的異動欄位
     * @param  ?array  $current  目前版本的原始紀錄
     * @return void
     */
    private function validateLinks(array $project, array &$fields, ?array $current = null): void
    {
        $issues = $this->store->all('issues');
        $children = array_filter($issues, fn ($i) => ($i['parentIssueId'] ?? '') === ($current['id'] ?? null));
        if ($fields['parentIssueId']) {
            $parent = $this->store->require('issues', $fields['parentIssueId']);
            $isSameProject = $parent['projectId'] === $project['id'];
            $isExecutionPair = $parent['type'] !== 'epic' && $fields['type'] !== 'epic';
            $isSelfParent = $parent['id'] === ($current['id'] ?? null);
            $createsExtraLevel = ! empty($parent['parentIssueId']) || count($children) > 0;
            if (! $isSameProject || ! $isExecutionPair || $isSelfParent || $createsExtraLevel) {
                DomainError::fail('INVALID_INPUT', '子任務限同專案一層, 請選擇其他一般任務作為父任務');
            }
            if (($current['parentIssueId'] ?? '') === $fields['parentIssueId'] && $fields['epicId'] !== $parent['epicId']) {
                DomainError::fail('INVALID_INPUT', '子任務所屬 Epic 隨父任務, 請從父任務調整');
            }
            $fields['epicId'] = $parent['epicId'];
        }
        foreach ($fields['relatedIssueIds'] as $id) {
            $target = $this->store->require('issues', $id);
            if ($target['projectId'] !== $project['id'] || $id === ($current['id'] ?? null)) {
                DomainError::fail('INVALID_INPUT', '只能關聯同專案的其他任務');
            }
            if (! in_array($current['id'] ?? '', $target['relatedIssueIds'] ?? [], true) && count($target['relatedIssueIds'] ?? []) >= 200) {
                DomainError::fail('INVALID_INPUT', '目標的關聯任務已達上限');
            }
        }
        if ($fields['epicId']) {
            $epic = $this->store->require('issues', $fields['epicId']);
            if ($epic['projectId'] !== $project['id'] || $epic['type'] !== 'epic' || $fields['type'] === 'epic' || $epic['id'] === ($current['id'] ?? null)) {
                DomainError::fail('INVALID_INPUT', 'Epic 必須是同專案的父項目, 不可巢狀或自我連結');
            }
        }
        if ($fields['assignee'] && $fields['assignee'] !== ($current['assignee'] ?? '') && ! $this->access->forUser($fields['assignee'])->can($project['id'], 'project.read')) {
            DomainError::fail('INVALID_INPUT', '負責人必須有目前專案的存取權');
        }
        if ($fields['type'] === 'epic' && $fields['estimateMinutes']) {
            DomainError::fail('INVALID_INPUT', 'Epic 的預估工時由子任務加總, 請在子任務填寫');
        }
        if (! $current) {
            return;
        }
        $epicChildren = array_filter($issues, fn ($i) => ($i['epicId'] ?? '') === $current['id']);
        if ($fields['type'] === 'epic') {
            if ($children) {
                DomainError::fail('INVALID_INPUT', '請先移轉子任務, 再改成 Epic');
            }
            if ($current['type'] !== 'epic') {
                foreach ($this->store->all('timeEntries') as $entry) {
                    if ($entry['issueId'] === $current['id'] && empty($entry['voidedAt'])) {
                        DomainError::fail('INVALID_INPUT', '已有工時的任務不可改成 Epic');
                    }
                }
            }
            if ($fields['state'] !== $current['state'] && Fields::isClosed($fields)) {
                foreach ($epicChildren as $child) {
                    if (! Fields::isClosed($child)) {
                        DomainError::fail('INVALID_INPUT', '還有未結案子任務, 請先關閉後再關閉 Epic');
                    }
                }
            }
        } elseif ($epicChildren) {
            DomainError::fail('INVALID_INPUT', '請先移轉子任務, 再變更 Epic 類型');
        }
    }

    // 關聯是雙向資料, 所有端點在同一交易更新 revision／history
    /**
     * 在同一交易同步關聯兩端的版本與歷史。
     *
     * @param  array  $issue  目前任務內容
     * @param  array  $previous  異動前的關聯識別碼
     * @return void
     */
    private function syncRelations(array $issue, array $previous): void
    {
        foreach (array_unique([...$previous, ...$issue['relatedIssueIds']]) as $id) {
            $peer = $this->store->require('issues', $id);
            $ids = $peer['relatedIssueIds'] ?? [];
            $next = array_values(array_diff($ids, [$issue['id']]));
            if (in_array($id, $issue['relatedIssueIds'], true)) {
                $next[] = $issue['id'];
            }
            sort($next);
            if ($ids !== $next) {
                $this->writeIssue($peer, ['relatedIssueIds' => $next]);
            }
        }
    }

    /**
     * 只為實際變動的任務欄位增加版本與異動歷史。
     *
     * @param  array  $current  目前版本的原始紀錄
     * @param  array  $fields  已選取或驗證的異動欄位
     * @return array
     */
    private function writeIssue(array $current, array $fields): array
    {
        $changes = [];
        foreach ($fields as $field => $value) {
            $before = $current[$field] ?? ($field === 'relatedIssueIds' ? [] : '');
            if ($before !== $value) {
                $changes[] = [
                    'field' => $field,
                    'before' => $before,
                    'after' => $value,
                ];
            }
        }
        if (! $changes) {
            return $current;
        }
        $issue = [
            ...$current,
            ...$fields,
            'revision' => $current['revision'] + 1,
            'updatedAt' => $this->now(),
            'history' => [
                ...$current['history'],
                [
                    'id' => $this->id(),
                    'kind' => 'updated',
                    'actor' => $this->workspaceSession['user']['name'],
                    'at' => $this->now(),
                    'changes' => $changes,
                ],
            ],
        ];
        $this->store->put('issues', $issue['id'], $issue);

        return $issue;
    }

    /**
     * 驗證版本及關聯後更新任務，必要時同步子項Epic與通知。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function updateIssue(array $input): array
    {
        $current = $this->store->require('issues', $input['id'] ?? '');
        RecordRevision::assertCurrent($current, $input);
        $project = $this->store->require('projects', $current['projectId']);
        $fields = Fields::issue([...$current, ...$input], $project);
        $this->validateLinks($project, $fields, $current);
        $issue = $this->writeIssue($current, $fields);
        if ($issue['revision'] === $current['revision']) {
            return $issue;
        }
        $this->syncRelations($issue, $current['relatedIssueIds'] ?? []);
        if ($issue['epicId'] !== $current['epicId']) {
            foreach ($this->store->all('issues') as $child) {
                if (($child['parentIssueId'] ?? '') === $issue['id']) {
                    $this->writeIssue($child, ['epicId' => $issue['epicId']]);
                }
            }
        }
        if ($issue['assignee'] !== $current['assignee']) {
            $this->notify($issue, '指派任務', $this->workspaceSession['user']['name'].' 將任務指派給你', $issue['assignee']);
        }
        if ($issue['epicId']) {
            $parent = $this->store->require('issues', $issue['epicId']);
            if (Fields::isClosed($parent) && ! Fields::isClosed($issue)) {
                $this->notify($parent, 'Epic 需要重新確認', $this->workspaceSession['user']['name'].' 更新了未結案子任務 '.$issue['identifier'], $parent['assignee']);
            }
        }

        return $issue;
    }

    /**
     * 只為有權讀取任務的其他收件人建立通知預覽。
     *
     * @param  array  $issue  目前任務內容
     * @param  string  $subject  通知主旨
     * @param  string  $body  通知說明內容
     * @param  string  $recipientId  通知收件者識別碼
     * @return void
     */
    private function notify(array $issue, string $subject, string $body, string $recipientId): void
    {
        $member = $recipientId ? $this->store->get('members', $recipientId) : null;
        if (! $member || $recipientId === $this->workspaceSession['user']['id'] || ! $this->access->forUser($recipientId)->can($issue['projectId'], 'issue.read')) {
            return;
        }
        $notification = [
            'id' => $this->id(),
            'issueId' => $issue['id'],
            'projectId' => $issue['projectId'],
            'recipientId' => $recipientId,
            'email' => $member['email'],
            'subject' => '['.$issue['identifier'].'] '.$subject,
            'body' => $body."\n".$issue['title'],
            'status' => 'preview',
            'createdAt' => $this->now(),
        ];
        $this->store->put('notifications', $notification['id'], $notification);
    }

    /**
     * 驗證任務版本後新增留言並通知負責人。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function addComment(array $input): array
    {
        $current = $this->store->require('issues', $input['id'] ?? '');
        RecordRevision::assertCurrent($current, $input);
        $comment = [
            'id' => $this->id(),
            'content' => Fields::markdown($input['content'] ?? null, '留言', 2000, true),
            'author' => $this->workspaceSession['user']['name'],
            'createdAt' => $this->now(),
        ];
        $issue = [
            ...$current,
            'revision' => $current['revision'] + 1,
            'updatedAt' => $this->now(),
            'comments' => [...$current['comments'], $comment],
        ];
        $this->store->put('issues', $issue['id'], $issue);
        $this->notify($issue, '新留言', $comment['author'].'：'.$comment['content'], $issue['assignee']);

        return $issue;
    }
}
