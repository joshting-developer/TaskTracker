<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions as Permissions;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceService
{
    private RecordStore $store;

    public function __construct(private array $context)
    {
        $this->store = (new RecordStore)->forWorkspace($context['workspace']['id']);
    }

    private function now(): string { return now()->utc()->toISOString(); }
    private function id(): string { return (string) Str::uuid(); }

    public function execute(string $operation, array $input): array
    {
        $permission = match ($operation) {
            'getWorkspace' => 'project.read',
            'createProject', 'loadDemo' => 'project.create',
            'createIssue', 'updateIssue', 'addComment' => 'issue.write',
            'updateStatuses' => 'project.statuses.update',
            'logTime', 'updateTime', 'voidTime' => 'timeEntry.writeOwn',
            'createSavedView', 'updateSavedView', 'deleteSavedView' => 'savedView.writeOwn',
            default => DomainError::fail('NOT_FOUND', '找不到這個操作'),
        };
        Permissions::authorize($this->context, $permission);
        if ($operation === 'getWorkspace') return $this->workspace();
        $requestId = Fields::text($input['requestId'] ?? null, '操作識別碼', 100, true);
        $key = $this->context['user']['id'].':'.$requestId;
        $signature = hash('sha256', $this->canonical([$operation, $input]));
        // Controller 在同一交易內重新讀成員資格, 才進入本服務與重試結果
        $previous = $this->store->get('requests', $key);
        if ($previous) {
            if ($previous['signature'] !== $signature) DomainError::fail('REQUEST_CONFLICT', '操作識別碼已使用, 請重新送出');
            return $previous['result'];
        }
        $result = $this->$operation($input);
        $this->store->put('requests', $key, ['id' => $key, 'signature' => $signature, 'result' => $result]);
        return $result;
    }

    private function canonical(mixed $value): string
    {
        if (is_array($value)) {
            if (!array_is_list($value)) ksort($value);
            foreach ($value as &$item) $item = json_decode($this->canonical($item), true);
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function workspace(): array
    {
        $issues = $this->store->all('issues');
        usort($issues, fn ($a, $b) => strcmp($b['updatedAt'], $a['updatedAt']) ?: strcmp($a['identifier'], $b['identifier']));
        $userId = $this->context['user']['id'];
        return [
            'projects' => $this->store->all('projects'), 'issues' => $issues,
            'members' => $this->store->all('members'), 'timeEntries' => $this->store->all('timeEntries'),
            'notifications' => array_values(array_filter($this->store->all('notifications'), fn ($n) => in_array('notification.readTeam', $this->context['permissions'], true) || $n['recipientId'] === $userId)),
            'savedViews' => array_values(array_filter($this->store->all('savedViews'), fn ($v) => empty($v['archivedAt']) && ($v['userId'] === $userId || $v['visibility'] === 'team'))),
            'meta' => ['mode' => 'local', 'actor' => $this->context['user']['name'], 'userId' => $userId, 'role' => $this->context['role'], 'permissions' => $this->context['permissions'], 'workspace' => $this->context['workspace'], 'singleUser' => false],
        ];
    }

    private function createProject(array $input): array
    {
        $name = Fields::text($input['name'] ?? null, '專案名稱', 60, true);
        $code = strtoupper(Fields::text($input['code'] ?? null, '專案代碼', 8, true));
        if (!preg_match('/^[A-Z][A-Z0-9]{1,7}$/D', $code)) DomainError::fail('INVALID_INPUT', '專案代碼需為 2–8 位英文字母或數字, 並以英文字母開頭');
        $projects = $this->store->all('projects');
        if (in_array($code, array_column($projects, 'code'), true)) DomainError::fail('DUPLICATE_CODE', '這個專案代碼已使用');
        $project = ['id' => $this->id(), 'name' => $name, 'code' => $code, 'description' => Fields::text($input['description'] ?? '', '專案說明', 500), 'color' => ['#8d83ed', '#64b0a0', '#e5a25c', '#cf82ae', '#71a2d5'][count($projects) % 5], 'nextNumber' => 1, 'revision' => 1, 'statuses' => Fields::STATES, 'settingsHistory' => [], 'createdAt' => $this->now()];
        $this->store->put('projects', $project['id'], $project);
        return $project;
    }

    private function createIssue(array $input): array
    {
        $project = $this->store->require('projects', $input['projectId'] ?? '');
        $fields = Fields::issue($input, $project);
        $this->validateLinks($project, $fields);
        $issue = ['id' => $this->id(), 'projectId' => $project['id'], 'identifier' => $project['code'].'-'.$project['nextNumber'], ...$fields, 'revision' => 1, 'createdAt' => $this->now(), 'updatedAt' => $this->now(), 'comments' => [], 'history' => [['id' => $this->id(), 'kind' => 'created', 'actor' => $this->context['user']['name'], 'at' => $this->now(), 'changes' => []]]];
        $this->store->put('projects', $project['id'], [...$project, 'nextNumber' => $project['nextNumber'] + 1]);
        $this->store->put('issues', $issue['id'], $issue);
        $this->notify($issue, '指派任務', $this->context['user']['name'].' 將任務指派給你', $issue['assignee']);
        return $issue;
    }

    private function validateLinks(array $project, array $fields, ?array $current = null): void
    {
        if ($fields['type'] === 'epic' && $fields['blockedBy']) DomainError::fail('INVALID_INPUT', 'Epic 使用子任務關係, 前置依賴請設定在執行任務');
        $issues = $this->store->all('issues');
        $graph = array_column(array_filter($issues, fn ($i) => $i['projectId'] === $project['id']), null, 'id');
        foreach ($fields['blockedBy'] as $id) {
            $blocker = $this->store->require('issues', $id);
            if ($blocker['projectId'] !== $project['id'] || $blocker['type'] === 'epic' || $id === ($current['id'] ?? null)) DomainError::fail('INVALID_INPUT', '前置任務必須是同專案的其他執行任務');
            $stack = [$id]; $visited = [];
            while ($stack) {
                $next = array_pop($stack);
                if ($next === ($current['id'] ?? null)) DomainError::fail('INVALID_INPUT', '前置任務會形成循環依賴, 請調整關係');
                if (isset($visited[$next])) continue;
                $visited[$next] = true;
                array_push($stack, ...($graph[$next]['blockedBy'] ?? []));
            }
        }
        $changedDependencies = $fields['blockedBy'] !== ($current['blockedBy'] ?? []);
        if (Fields::category($fields, $project) === 'done' && (!$current || $fields['state'] !== $current['state'] || $changedDependencies)) {
            foreach ($fields['blockedBy'] as $id) if (Fields::category($graph[$id], $project) !== 'done') DomainError::fail('INVALID_INPUT', '還有未完成的前置任務, 請先完成或調整依賴後再結案');
        }
        if ($fields['epicId']) {
            $parent = $this->store->require('issues', $fields['epicId']);
            if ($parent['projectId'] !== $project['id'] || $parent['type'] !== 'epic' || $fields['type'] === 'epic' || $parent['id'] === ($current['id'] ?? null)) DomainError::fail('INVALID_INPUT', 'Epic 必須是同專案的父項目, 不可巢狀或自我連結');
        }
        if ($fields['assignee'] && $fields['assignee'] !== ($current['assignee'] ?? '') && !$this->store->get('members', $fields['assignee'])) DomainError::fail('INVALID_INPUT', '負責人必須是工作空間成員');
        if ($fields['type'] === 'epic' && $fields['estimateMinutes']) DomainError::fail('INVALID_INPUT', 'Epic 的預估工時由子任務加總, 請在子任務填寫');
        if (!$current) return;
        $children = array_filter($issues, fn ($i) => ($i['epicId'] ?? '') === $current['id']);
        if ($fields['type'] === 'epic') {
            foreach ($graph as $issue) if (in_array($current['id'], $issue['blockedBy'] ?? [], true)) DomainError::fail('INVALID_INPUT', '其他任務仍依賴這個任務, 請先移轉依賴再改成 Epic');
            if ($current['type'] !== 'epic') foreach ($this->store->all('timeEntries') as $entry) if ($entry['issueId'] === $current['id'] && empty($entry['voidedAt'])) DomainError::fail('INVALID_INPUT', '已有工時的任務不可改成 Epic');
            if ($fields['state'] !== $current['state'] && Fields::category($fields, $project) === 'done') foreach ($children as $child) if (!in_array(Fields::category($child, $project), ['done', 'canceled'], true)) DomainError::fail('INVALID_INPUT', '還有未結案子任務, 請先完成或取消後再驗收 Epic');
        } elseif ($children) DomainError::fail('INVALID_INPUT', '請先移轉子任務, 再變更 Epic 類型');
    }

    private function revision(array $record, array $input, string $label = '任務'): void
    {
        if (($input['expectedRevision'] ?? null) !== $record['revision']) DomainError::fail('REVISION_CONFLICT', $label.'已被更新, 請重新載入後再修改');
    }

    private function updateIssue(array $input): array
    {
        $current = $this->store->require('issues', $input['id'] ?? '');
        $this->revision($current, $input);
        $project = $this->store->require('projects', $current['projectId']);
        $fields = Fields::issue([...$current, ...$input], $project);
        $this->validateLinks($project, $fields, $current);
        $changes = [];
        foreach (Fields::ISSUE_FIELDS as $field) {
            $before = $current[$field] ?? ($field === 'blockedBy' ? [] : null);
            if ($before !== $fields[$field]) $changes[] = ['field' => $field, 'before' => $before, 'after' => $fields[$field]];
        }
        if (!$changes) return $current;
        $issue = [...$current, ...$fields, 'revision' => $current['revision'] + 1, 'updatedAt' => $this->now(), 'history' => [...$current['history'], ['id' => $this->id(), 'kind' => 'updated', 'actor' => $this->context['user']['name'], 'at' => $this->now(), 'changes' => $changes]]];
        $this->store->put('issues', $issue['id'], $issue);
        if ($issue['assignee'] !== $current['assignee']) $this->notify($issue, '指派任務', $this->context['user']['name'].' 將任務指派給你', $issue['assignee']);
        if ($issue['epicId']) {
            $parent = $this->store->require('issues', $issue['epicId']);
            if (Fields::category($parent, $project) === 'done' && !in_array(Fields::category($issue, $project), ['done', 'canceled'], true)) $this->notify($parent, 'Epic 需要重新確認', $this->context['user']['name'].' 更新了未結案子任務 '.$issue['identifier'], $parent['assignee']);
        }
        return $issue;
    }

    private function notify(array $issue, string $subject, string $body, string $recipientId): void
    {
        $member = $recipientId ? $this->store->get('members', $recipientId) : null;
        if (!$member || $recipientId === $this->context['user']['id']) return;
        $notification = ['id' => $this->id(), 'issueId' => $issue['id'], 'projectId' => $issue['projectId'], 'recipientId' => $recipientId, 'email' => $member['email'], 'subject' => '['.$issue['identifier'].'] '.$subject, 'body' => $body."\n".$issue['title'], 'status' => 'preview', 'createdAt' => $this->now()];
        $this->store->put('notifications', $notification['id'], $notification);
    }

    private function addComment(array $input): array
    {
        $current = $this->store->require('issues', $input['id'] ?? '');
        $this->revision($current, $input);
        $comment = ['id' => $this->id(), 'content' => Fields::text($input['content'] ?? null, '留言', 2000, true), 'author' => $this->context['user']['name'], 'createdAt' => $this->now()];
        $issue = [...$current, 'revision' => $current['revision'] + 1, 'updatedAt' => $this->now(), 'comments' => [...$current['comments'], $comment]];
        $this->store->put('issues', $issue['id'], $issue);
        $this->notify($issue, '新留言', $comment['author'].'：'.$comment['content'], $issue['assignee']);
        return $issue;
    }

    private function updateStatuses(array $input): array
    {
        $project = $this->store->require('projects', $input['projectId'] ?? '');
        $this->revision($project, $input, '專案設定');
        $states = $input['statuses'] ?? null;
        if (!is_array($states) || !array_is_list($states) || count($states) < 2 || count($states) > 12) DomainError::fail('INVALID_INPUT', '請設定 2–12 個狀態');
        $states = array_map(function ($s) {
            if (!is_array($s)) DomainError::fail('INVALID_INPUT', '狀態格式不正確');
            $id = Fields::text($s['id'] ?? null, '狀態 ID', 100, true);
            $color = Fields::text($s['color'] ?? null, '顏色', 7, true);
            if (!preg_match('/^[A-Za-z0-9_-]+$/D', $id) || !preg_match('/^#[0-9a-f]{6}$/iD', $color)) DomainError::fail('INVALID_INPUT', '狀態 ID 或顏色不正確');
            return ['id' => $id, 'label' => Fields::text($s['label'] ?? null, '狀態名稱', 30, true), 'category' => Fields::choice($s['category'] ?? null, ['backlog', 'active', 'done', 'canceled'], '分類'), 'color' => $color];
        }, $states);
        if (count(array_unique(array_column($states, 'id'))) !== count($states) || count(array_unique(array_column($states, 'label'))) !== count($states)) DomainError::fail('INVALID_INPUT', '狀態 ID 與名稱不可重複');
        if (!in_array('done', array_column($states, 'category'), true) || !array_intersect(['active', 'backlog'], array_column($states, 'category'))) DomainError::fail('INVALID_INPUT', '至少需要一個完成與一個未結案狀態');
        foreach ($this->store->all('issues') as $issue) if ($issue['projectId'] === $project['id'] && !in_array($issue['state'], array_column($states, 'id'), true)) DomainError::fail('INVALID_INPUT', '使用中的狀態不能移除, 請先移轉任務');
        $next = [...$project, 'statuses' => $states, 'revision' => $project['revision'] + 1, 'settingsHistory' => [...($project['settingsHistory'] ?? []), ['actor' => $this->context['user']['name'], 'at' => $this->now(), 'before' => $project['statuses'] ?? Fields::STATES, 'after' => $states]]];
        $this->store->put('projects', $project['id'], $next);
        return $next;
    }

    private function saveTime(array $input, ?array $current = null): array
    {
        $userId = $this->context['user']['id'];
        if ($current && ($current['userId'] !== $userId || !empty($current['voidedAt']))) DomainError::fail('FORBIDDEN', '只能修改自己的有效工時');
        $issue = $this->store->require('issues', $current['issueId'] ?? $input['issueId'] ?? '');
        if ($issue['type'] === 'epic') DomainError::fail('INVALID_INPUT', '請將工時登記在 Epic 的子任務');
        $date = Fields::date($input['workDate'] ?? null);
        if ($date > now('Asia/Taipei')->toDateString()) DomainError::fail('INVALID_INPUT', '不能填報未來日期的工時');
        $minutes = Fields::integer($input['minutes'] ?? null, '工時分鐘', 1, 1440);
        $daily = array_sum(array_column(array_filter($this->store->all('timeEntries'), fn ($e) => empty($e['voidedAt']) && $e['userId'] === $userId && $e['workDate'] === $date && $e['id'] !== ($current['id'] ?? '')), 'minutes'));
        if ($daily + $minutes > 1440) DomainError::fail('INVALID_INPUT', '同一人同一天的工時不能超過 24 小時');
        $note = Fields::text($input['note'] ?? '', '工時說明', 1000);
        $entry = [...($current ?? ['id' => $this->id(), 'issueId' => $issue['id'], 'projectId' => $issue['projectId'], 'userId' => $userId, 'createdAt' => $this->now(), 'history' => []]), 'workDate' => $date, 'minutes' => $minutes, 'note' => $note, 'revision' => ($current['revision'] ?? 0) + 1, 'updatedAt' => $this->now()];
        $entry['history'][] = ['actor' => $this->context['user']['name'], 'at' => $this->now(), 'before' => $current ? array_intersect_key($current, array_flip(['workDate', 'minutes', 'note'])) : null, 'after' => ['workDate' => $date, 'minutes' => $minutes, 'note' => $note]];
        $this->store->put('timeEntries', $entry['id'], $entry);
        return $entry;
    }

    private function logTime(array $input): array { return $this->saveTime($input); }
    private function updateTime(array $input): array
    {
        $entry = $this->store->require('timeEntries', $input['id'] ?? '');
        $this->revision($entry, $input, '工時');
        return $this->saveTime($input, $entry);
    }

    private function voidTime(array $input): array
    {
        $entry = $this->store->require('timeEntries', $input['id'] ?? '');
        if ($entry['userId'] !== $this->context['user']['id']) DomainError::fail('FORBIDDEN', '只能作廢自己的工時');
        $this->revision($entry, $input, '工時');
        if (!empty($entry['voidedAt'])) return $entry;
        $next = [...$entry, 'voidedAt' => $this->now(), 'updatedAt' => $this->now(), 'revision' => $entry['revision'] + 1, 'history' => [...$entry['history'], ['actor' => $this->context['user']['name'], 'at' => $this->now(), 'kind' => 'voided']]];
        $this->store->put('timeEntries', $next['id'], $next);
        return $next;
    }

    private function viewFields(array $input, string $projectId): array
    {
        $project = $this->store->require('projects', $projectId);
        $filters = $input['filters'] ?? [];
        if (!is_array($filters) || array_diff(array_keys($filters), array_keys(Fields::FILTERS))) DomainError::fail('INVALID_INPUT', '視圖含不支援的篩選欄位');
        $result = [];
        foreach (Fields::FILTERS as $key => $default) $result[$key] = Fields::text($filters[$key] ?? $default, '篩選條件', $key === 'search' ? 180 : 100);
        foreach (['scope' => ['all', 'mine', 'done'], 'priority' => ['', 'urgent', 'high', 'normal', 'low'], 'type' => ['', 'task', 'bug', 'feature'], 'condition' => ['', 'open', 'blocked', 'review', 'overdue', 'unassigned'], 'sort' => ['updated', 'priority', 'due'], 'view' => ['list', 'board']] as $key => $allowed) Fields::choice($result[$key], $allowed, '視圖篩選');
        if ($result['state'] && !in_array($result['state'], array_column($project['statuses'] ?? Fields::STATES, 'id'), true)) DomainError::fail('INVALID_INPUT', '篩選的狀態不存在');
        if ($result['assignee'] && !$this->store->get('members', $result['assignee'])) DomainError::fail('INVALID_INPUT', '篩選的成員不存在');
        if ($result['epicId'] && $result['epicId'] !== 'unlinked') {
            $epic = $this->store->get('issues', $result['epicId']);
            if (!$epic || $epic['type'] !== 'epic' || $epic['projectId'] !== $projectId) DomainError::fail('INVALID_INPUT', '篩選的 Epic 不存在');
        }
        return ['name' => Fields::text($input['name'] ?? null, '視圖名稱', 60, true), 'visibility' => Fields::choice($input['visibility'] ?? 'private', ['private', 'team'], '分享範圍'), 'filters' => $result];
    }

    private function createSavedView(array $input): array
    {
        $projectId = Fields::text($input['projectId'] ?? null, '專案', 100, true);
        $view = ['id' => $this->id(), 'projectId' => $projectId, 'userId' => $this->context['user']['id'], ...$this->viewFields($input, $projectId), 'revision' => 1, 'createdAt' => $this->now(), 'updatedAt' => $this->now()];
        $this->store->put('savedViews', $view['id'], $view);
        return $view;
    }

    private function ownedView(array $input): array
    {
        $view = $this->store->require('savedViews', $input['id'] ?? '');
        if ($view['userId'] !== $this->context['user']['id'] || !empty($view['archivedAt'])) DomainError::fail('NOT_FOUND', '找不到可修改的視圖');
        $this->revision($view, $input, '視圖');
        return $view;
    }

    private function updateSavedView(array $input): array
    {
        $current = $this->ownedView($input);
        $view = [...$current, ...$this->viewFields($input, $current['projectId']), 'revision' => $current['revision'] + 1, 'updatedAt' => $this->now()];
        $this->store->put('savedViews', $view['id'], $view);
        return $view;
    }

    private function deleteSavedView(array $input): array
    {
        $current = $this->ownedView($input);
        $this->store->put('savedViews', $current['id'], [...$current, 'archivedAt' => $this->now(), 'updatedAt' => $this->now(), 'revision' => $current['revision'] + 1]);
        return ['id' => $current['id']];
    }

    private function loadDemo(array $input): array
    {
        if (in_array('DEMO', array_column($this->store->all('projects'), 'code'), true)) DomainError::fail('DEMO_EXISTS', '示範專案已存在, 不會重複載入');
        $project = $this->createProject(['name' => '產品第一版 · 示範', 'code' => 'DEMO', 'description' => '用這個示範專案試試清單、看板與任務修改, 內容不代表正式需求']);
        $rows = [
            ['整理第一版需求與使用流程', 'done', 'high', 'task'], ['建立專案與任務資料結構', 'done', 'normal', 'task'],
            ['設計任務清單的搜尋與篩選', 'in_progress', 'high', 'feature'], ['讓看板拖曳與任務狀態同步', 'in_progress', 'normal', 'feature'],
            ['手機上的表單需要更好操作', 'todo', 'high', 'bug'], ['補上任務留言與異動紀錄', 'todo', 'normal', 'feature'],
            ['確認 Laravel API 部署方式', 'todo', 'normal', 'task'], ['討論下一版的常用視圖', 'backlog', 'low', 'feature'], ['研究適合團隊的登入流程', 'backlog', 'normal', 'task'],
        ];
        foreach ($rows as $index => [$title, $state, $priority, $type]) $this->createIssue(['projectId' => $project['id'], 'title' => $title, 'state' => $state, 'priority' => $priority, 'type' => $type, 'assignee' => in_array($index, [4, 6, 7, 8], true) ? '' : $this->context['user']['id']]);
        return ['projectId' => $project['id']];
    }
}
