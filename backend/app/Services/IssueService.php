<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Domain\RecordRevision;
use App\Repositories\IssueChildrenRepository;
use App\Repositories\RecordStore;
use Illuminate\Support\Str;

/**
 * 管理任務、父子與雙向關聯，維持同交易歷史和通知預覽。
 */
class IssueService
{
    /**
     * 設定此服務使用的資料入口與可信任相依。
     *
     * @param  RecordStore  $store  限定工作空間的資料存取入口
     * @param  array  $workspaceSession  從後端Session重新驗證的工作空間身分與權限
     * @param  ProjectAccess  $access  本次操作共用的Project權限檢查
     * @param  \Closure(): ProjectBoards  $boards  建立卡片帶boardId時才取得的看板用例
     * @param  IssueChildrenRepository  $children  同交易批次子任務更新
     */
    public function __construct(private RecordStore $store, private array $workspaceSession, private ProjectAccess $access, private \Closure $boards, private IssueChildrenRepository $children) {}

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
            ($this->boards)()->includeCreatedIssue(Fields::text($input['boardId'], '看板', 100, true), $issue);
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
        $links = $this->store->issueLinks([$fields['parentIssueId'], $fields['epicId'], ...$fields['relatedIssueIds']], $current['id'] ?? '');
        $children = $current && $this->store->issueHasChildren($project['id'], $current['id']);
        if ($fields['parentIssueId']) {
            $parent = $links[$fields['parentIssueId']] ?? DomainError::fail('NOT_FOUND', '找不到這筆資料, 請重新載入');
            $isSameProject = $parent->projectId === $project['id'];
            $isExecutionPair = $parent->type !== 'epic' && $fields['type'] !== 'epic';
            $isSelfParent = $parent->id === ($current['id'] ?? null);
            $createsExtraLevel = ! empty($parent->parentIssueId) || $children;
            if (! $isSameProject || ! $isExecutionPair || $isSelfParent || $createsExtraLevel) {
                DomainError::fail('INVALID_INPUT', '子任務限同專案一層, 請選擇其他一般任務作為父任務');
            }
            if (($current['parentIssueId'] ?? '') === $fields['parentIssueId'] && $fields['epicId'] !== $parent->epicId) {
                DomainError::fail('INVALID_INPUT', '子任務所屬 Epic 隨父任務, 請從父任務調整');
            }
            $fields['epicId'] = $parent->epicId;
        }
        foreach ($fields['relatedIssueIds'] as $id) {
            $target = $links[$id] ?? DomainError::fail('NOT_FOUND', '找不到這筆資料, 請重新載入');
            if ($target->projectId !== $project['id'] || $id === ($current['id'] ?? null)) {
                DomainError::fail('INVALID_INPUT', '只能關聯同專案的其他任務');
            }
            if (! $target->alreadyRelated && $target->relatedCount >= 200) {
                DomainError::fail('INVALID_INPUT', '目標的關聯任務已達上限');
            }
        }
        if ($fields['epicId']) {
            if (! isset($links[$fields['epicId']])) {
                $links += $this->store->issueLinks([$fields['epicId']]);
            }
            $epic = $links[$fields['epicId']] ?? DomainError::fail('NOT_FOUND', '找不到這筆資料, 請重新載入');
            if ($epic->projectId !== $project['id'] || $epic->type !== 'epic' || $fields['type'] === 'epic' || $epic->id === ($current['id'] ?? null)) {
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
        $epicChildren = $this->store->issueHasChildren($project['id'], $current['id'], epic: true);
        if ($fields['type'] === 'epic') {
            if ($children) {
                DomainError::fail('INVALID_INPUT', '請先移轉子任務, 再改成 Epic');
            }
            if ($current['type'] !== 'epic') {
                if ($this->store->issueHasActiveTime($project['id'], $current['id'])) {
                    DomainError::fail('INVALID_INPUT', '已有工時的任務不可改成 Epic');
                }
            }
            if ($fields['state'] !== $current['state'] && Fields::isClosed($fields)) {
                if ($this->store->issueHasChildren($project['id'], $current['id'], epic: true, unclosed: true)) {
                    DomainError::fail('INVALID_INPUT', '還有未結案子任務, 請先關閉後再關閉 Epic');
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
     * TT-250：依版本永久刪除卡片。有工時、Epic所屬卡或子任務時拒絕；關聯另一端與收錄看板在同一交易增加revision。
     *
     * @param  array  $input  id、projectId、expectedRevision與requestId
     * @return array{id:string,projectId:string,identifier:string,revision:int} 刪除前最後版本＋1，供重播回傳相同結果
     *
     * @throws DomainError 版本衝突或仍有不可刪除的關聯資料
     */
    public function deleteIssue(array $input): array
    {
        $issue = $this->store->require('issues', $input['id'] ?? '');
        if ($issue['projectId'] !== ($input['projectId'] ?? '')) {
            DomainError::fail('NOT_FOUND', '找不到這個專案內的任務');
        }
        RecordRevision::assertCurrent($issue, $input);
        if ($this->store->issueHasAnyTime($issue['projectId'], $issue['id'])) {
            DomainError::fail('CONFLICT', '這張卡片已有工時紀錄，不能刪除；請改為關閉');
        }
        if ($this->store->issueHasChildren($issue['projectId'], $issue['id'], true) || $this->store->issueHasChildren($issue['projectId'], $issue['id'])) {
            DomainError::fail('CONFLICT', '請先移出所屬卡片或子任務，再刪除這張卡片');
        }
        foreach ($issue['relatedIssueIds'] ?? [] as $peerId) {
            $peer = $this->store->require('issues', $peerId);
            $this->writeIssue($peer, ['relatedIssueIds' => array_values(array_diff($peer['relatedIssueIds'] ?? [], [$issue['id']]))]);
        }
        foreach ($this->store->all('boards') as $board) {
            if ($board['projectId'] === $issue['projectId'] && in_array($issue['id'], $board['selectedIssueIds'] ?? [], true)) {
                $this->store->put('boards', $board['id'], [...$board, 'selectedIssueIds' => array_values(array_diff($board['selectedIssueIds'], [$issue['id']])),
                    'revision' => $board['revision'] + 1, 'updatedAt' => $this->now()]);
            }
        }
        $this->store->deleteIssue($issue['id'], ['actor' => $this->workspaceSession['user']['name'], 'at' => $this->now(),
            'issueId' => $issue['id'], 'identifier' => $issue['identifier'], 'title' => $issue['title']]);

        return ['id' => $issue['id'], 'projectId' => $issue['projectId'], 'identifier' => $issue['identifier'], 'revision' => $issue['revision'] + 1];
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
            $this->syncChildrenEpic($issue);
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
     * 只為實際換Epic的子卡追加版本與歷史, 不載舊活動
     *
     * @param  array  $issue  已更新且授權的父任務
     * @return void
     */
    private function syncChildrenEpic(array $issue): void
    {
        $scope = $this->children->scope($this->workspaceSession['workspace']['id'], $issue['projectId'], $issue['id'], $issue['epicId']);
        foreach ($this->children->batches($scope) as $rows) {
            $changes = [];
            $at = $this->now();
            foreach ($rows as $row) {
                $before = $row->epic_public_id ?? '';
                if ($before === $issue['epicId']) {
                    continue;
                }
                $changes[] = ['id' => (int) $row->id, 'revision' => (int) $row->revision, 'position' => (int) $row->history_position,
                    'event' => ['id' => $this->id(), 'kind' => 'updated', 'actor' => $this->workspaceSession['user']['name'], 'at' => $at,
                        'changes' => [['field' => 'epicId', 'before' => $before, 'after' => $issue['epicId']]]]];
            }
            if ($this->children->apply($scope, $changes, $at) !== count($changes)) {
                DomainError::fail('CONFLICT', '子任務版本已更新, 請重新載入');
            }
        }
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
