<?php

namespace App\Services;

use App\Authorization\WorkspacePermissions as Permissions;
use App\Authorization\ProjectAccess;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Repositories\RecordStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceService
{
    private RecordStore $store;
    private ProjectAccess $access;

    public function __construct(private array $context)
    {
        $this->store = (new RecordStore)->forWorkspace($context['workspace']['id']);
        $this->access = new ProjectAccess($this->store, $context);
    }

    private function now(): string { return now()->utc()->toISOString(); }
    private function id(): string { return (string) Str::uuid(); }

    public function execute(string $operation, array $input): array
    {
        $this->authorizeOperation($operation, $input);
        if ($operation === 'getWorkspace') return $this->workspace();
        if ($operation === 'getProjectMembers') return (new ProjectMembers($this->store, $this->context))->listing($input['projectId']);
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

    private function authorizeOperation(string $operation, array $input): void
    {
        Permissions::authorize($this->context, 'workspace.read');
        if ($operation === 'getWorkspace') return;
        if (in_array($operation, ['createProject', 'loadDemo'], true)) { Permissions::authorize($this->context, 'project.create'); return; }
        [$kind, $key, $permission] = match ($operation) {
            'createIssue' => ['projects', 'projectId', 'issue.write'],
            'updateIssue', 'addComment' => ['issues', 'id', 'issue.write'],
            'updateProject' => ['projects', 'id', 'project.update'],
            'updateStatuses' => ['projects', 'projectId', 'project.statuses.update'],
            'createBoard' => ['projects', 'projectId', 'board.manage'],
            'updateBoard', 'archiveBoard' => ['boards', 'id', 'board.manage'],
            'logTime' => ['issues', 'issueId', 'timeEntry.writeOwn'],
            'updateTime', 'voidTime' => ['timeEntries', 'id', 'timeEntry.writeOwn'],
            'getProjectMembers', 'setProjectMember', 'removeProjectMember' => ['projects', 'projectId', 'project.members.manage'],
            default => DomainError::fail('NOT_FOUND', '找不到這個操作'),
        };
        if ($kind === 'boards' && ($input[$key] ?? '') === 'default') DomainError::fail('INVALID_INPUT', 'Default 是固定看板, 不可修改或移除');
        $resource = $this->store->require($kind, Fields::text($input[$key] ?? null, '資料識別碼', 100, true));
        $projectId = $kind === 'projects' ? $resource['id'] : $resource['projectId'];
        $this->access->require($projectId, $permission);
        if ($kind === 'timeEntries' && $resource['userId'] !== $this->context['user']['id']) DomainError::fail('FORBIDDEN', '只能修改自己的工時');
        if ($operation === 'createIssue' && !empty($input['boardId']) && $input['boardId'] !== 'default') $this->access->require($projectId, 'board.manage');
        // 同一 requestId 的快取也必須在當次資源授權後才讀取
    }

    private function setProjectMember(array $input): array { return (new ProjectMembers($this->store, $this->context))->change($input, false); }
    private function removeProjectMember(array $input): array { return (new ProjectMembers($this->store, $this->context))->change($input, true); }

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
        $projects = [];
        $memberIds = [$this->context['user']['id']];
        foreach ($this->store->all('projects') as $project) {
            if (!$this->access->can($project['id'], 'project.read')) continue;
            $eligible = array_column($this->access->eligibleMembers($project['id']), 'id');
            $memberIds = [...$memberIds, ...$eligible];
            // capabilities 是此操作者目前的 scope 結果, 不存回原 Project
            $projects[] = [...$project, 'permissions' => $this->access->permissions($project['id']), 'accessSource' => $this->access->source($project['id']), 'memberIds' => $eligible];
        }
        $issueProjects = array_column(array_filter($projects, fn ($p) => $this->access->can($p['id'], 'issue.read')), 'id');
        $issues = array_values(array_filter($this->store->all('issues'), fn ($i) => in_array($i['projectId'], $issueProjects, true)));
        usort($issues, fn ($a, $b) => strcmp($b['updatedAt'], $a['updatedAt']) ?: strcmp($a['identifier'], $b['identifier']));
        $issueIds = array_column($issues, 'id');
        $userId = $this->context['user']['id'];
        $teamMembers = in_array('workspace.members.read', $this->context['permissions'], true);
        return [
            'projects' => $projects, 'issues' => $issues,
            'boards' => array_values(array_filter((new ProjectBoards($this->store, $this->context))->listing(), fn ($b) => in_array($b['projectId'], $issueProjects, true))),
            'members' => array_values(array_filter($this->store->all('members'), fn ($m) => $teamMembers || in_array($m['id'], $memberIds, true))),
            'timeEntries' => array_values(array_filter($this->store->all('timeEntries'), fn ($e) => in_array($e['issueId'], $issueIds, true) && ($this->access->can($e['projectId'], 'timeEntry.readTeam') || ($e['userId'] === $userId && $this->access->can($e['projectId'], 'timeEntry.readOwn'))))),
            'notifications' => array_values(array_filter($this->store->all('notifications'), fn ($n) => in_array($n['issueId'], $issueIds, true) && ($this->access->can($n['projectId'], 'notification.readTeam') || $n['recipientId'] === $userId))),
            'meta' => ['mode' => 'local', 'actor' => $this->context['user']['name'], 'userId' => $userId, 'role' => $this->context['role'], 'permissions' => $this->context['permissions'], 'workspace' => $this->context['workspace'], 'singleUser' => false, 'projectLeadIds' => !in_array('project.create', $this->context['permissions'], true) ? [] : array_column(array_filter($this->store->all('members'), fn ($m) => empty($m['disabledAt']) && in_array('project.readAny', Permissions::resolve($m['role']), true)), 'id')],
        ];
    }

    private function createBoard(array $input): array { return (new ProjectBoards($this->store, $this->context))->create($input); }
    private function updateBoard(array $input): array { return (new ProjectBoards($this->store, $this->context))->update($input); }
    private function archiveBoard(array $input): array { return (new ProjectBoards($this->store, $this->context))->archive($input); }

    private function createProject(array $input): array
    {
        $name = Fields::text($input['name'] ?? null, '專案名稱', 60, true);
        $code = strtoupper(Fields::text($input['code'] ?? null, '專案代碼', 8, true));
        if (!preg_match('/^[A-Z][A-Z0-9]{1,7}$/D', $code)) DomainError::fail('INVALID_INPUT', '專案代碼需為 2–8 位英文字母或數字, 並以英文字母開頭');
        $projects = $this->store->all('projects');
        if (in_array($code, array_column($projects, 'code'), true)) DomainError::fail('DUPLICATE_CODE', '這個專案代碼已使用');
        $leadId = $this->projectLead($input['leadId'] ?? $this->context['user']['id']);
        $project = ['id' => $this->id(), 'name' => $name, 'code' => $code, 'leadId' => $leadId, 'description' => Fields::text($input['description'] ?? '', '專案說明', 500), 'color' => ['#8d83ed', '#64b0a0', '#e5a25c', '#cf82ae', '#71a2d5'][count($projects) % 5], 'nextNumber' => 1, 'revision' => 1, 'statuses' => Fields::STATES, 'settingsHistory' => [], 'createdAt' => $this->now()];
        $this->store->put('projects', $project['id'], $project);
        if ($leadId && !$this->access->forUser($leadId)->can($project['id'], 'project.read')) DomainError::fail('INVALID_INPUT', '請先將一般成員加入專案, 再指派為專案負責人');
        return $project;
    }

    private function projectLead(mixed $value, ?string $projectId = null): string
    {
        $id = Fields::text($value, '專案負責人', 100);
        if ($id !== '') {
            $member = $this->store->get('members', $id);
            if (!$member || !empty($member['disabledAt']) || ($projectId && !$this->access->forUser($id)->can($projectId, 'project.read'))) DomainError::fail('INVALID_INPUT', '專案負責人必須是可存取目前專案的有效成員');
        }
        return $id;
    }

    private function updateProject(array $input): array
    {
        $project = $this->store->require('projects', $input['id'] ?? '');
        $this->revision($project, $input, '專案');
        if (isset($input['code']) && $input['code'] !== $project['code']) DomainError::fail('INVALID_INPUT', '專案代碼建立後不可修改');
        $fields = [
            'name' => Fields::text($input['name'] ?? $project['name'], '專案名稱', 60, true),
            'description' => Fields::text($input['description'] ?? $project['description'] ?? '', '專案介紹', 500),
            'leadId' => $this->projectLead($input['leadId'] ?? $project['leadId'] ?? '', isset($input['leadId']) && $input['leadId'] !== ($project['leadId'] ?? '') ? $project['id'] : null),
        ];
        $changes = [];
        foreach ($fields as $field => $value) if (($project[$field] ?? '') !== $value) $changes[] = ['field' => $field, 'before' => $project[$field] ?? '', 'after' => $value];
        if (!$changes) return $project;
        $next = [...$project, ...$fields, 'revision' => $project['revision'] + 1, 'updatedAt' => $this->now(), 'detailsHistory' => [...($project['detailsHistory'] ?? []), ['actor' => $this->context['user']['name'], 'at' => $this->now(), 'changes' => $changes]]];
        $this->store->put('projects', $next['id'], $next);
        return $next;
    }

    private function createIssue(array $input): array
    {
        $project = $this->store->require('projects', $input['projectId'] ?? '');
        $fields = Fields::issue($input, $project);
        $this->validateLinks($project, $fields);
        $issue = ['id' => $this->id(), 'projectId' => $project['id'], 'identifier' => $project['code'].'-'.$project['nextNumber'], ...$fields, 'revision' => 1, 'createdAt' => $this->now(), 'updatedAt' => $this->now(), 'comments' => [], 'history' => [['id' => $this->id(), 'kind' => 'created', 'actor' => $this->context['user']['name'], 'at' => $this->now(), 'changes' => []]]];
        $this->store->put('projects', $project['id'], [...$project, 'nextNumber' => $project['nextNumber'] + 1]);
        $this->store->put('issues', $issue['id'], $issue);
        $this->syncRelations($issue, []);
        if (!empty($input['boardId']) && $input['boardId'] !== 'default') {
            (new ProjectBoards($this->store, $this->context))->includeCreatedIssue(Fields::text($input['boardId'], '看板', 100, true), $issue);
        }
        $this->notify($issue, '指派任務', $this->context['user']['name'].' 將任務指派給你', $issue['assignee']);
        return $issue;
    }

    private function validateLinks(array $project, array &$fields, ?array $current = null): void
    {
        $issues = $this->store->all('issues');
        $children = array_filter($issues, fn ($i) => ($i['parentIssueId'] ?? '') === ($current['id'] ?? null));
        if ($fields['parentIssueId']) {
            $parent = $this->store->require('issues', $fields['parentIssueId']);
            if ($parent['projectId'] !== $project['id'] || $parent['type'] === 'epic' || $fields['type'] === 'epic' || $parent['id'] === ($current['id'] ?? null) || !empty($parent['parentIssueId']) || $children) DomainError::fail('INVALID_INPUT', '子任務限同專案一層, 請選擇其他一般任務作為父任務');
            if (($current['parentIssueId'] ?? '') === $fields['parentIssueId'] && $fields['epicId'] !== $parent['epicId']) DomainError::fail('INVALID_INPUT', '子任務所屬 Epic 隨父任務, 請從父任務調整');
            $fields['epicId'] = $parent['epicId'];
        }
        foreach ($fields['relatedIssueIds'] as $id) {
            $target = $this->store->require('issues', $id);
            if ($target['projectId'] !== $project['id'] || $id === ($current['id'] ?? null)) DomainError::fail('INVALID_INPUT', '只能關聯同專案的其他任務');
            if (!in_array($current['id'] ?? '', $target['relatedIssueIds'] ?? [], true) && count($target['relatedIssueIds'] ?? []) >= 200) DomainError::fail('INVALID_INPUT', '目標的關聯任務已達上限');
        }
        if ($fields['epicId']) {
            $epic = $this->store->require('issues', $fields['epicId']);
            if ($epic['projectId'] !== $project['id'] || $epic['type'] !== 'epic' || $fields['type'] === 'epic' || $epic['id'] === ($current['id'] ?? null)) DomainError::fail('INVALID_INPUT', 'Epic 必須是同專案的父項目, 不可巢狀或自我連結');
        }
        if ($fields['assignee'] && $fields['assignee'] !== ($current['assignee'] ?? '') && !$this->access->forUser($fields['assignee'])->can($project['id'], 'project.read')) DomainError::fail('INVALID_INPUT', '負責人必須有目前專案的存取權');
        if ($fields['type'] === 'epic' && $fields['estimateMinutes']) DomainError::fail('INVALID_INPUT', 'Epic 的預估工時由子任務加總, 請在子任務填寫');
        if (!$current) return;
        $epicChildren = array_filter($issues, fn ($i) => ($i['epicId'] ?? '') === $current['id']);
        if ($fields['type'] === 'epic') {
            if ($children) DomainError::fail('INVALID_INPUT', '請先移轉子任務, 再改成 Epic');
            if ($current['type'] !== 'epic') foreach ($this->store->all('timeEntries') as $entry) if ($entry['issueId'] === $current['id'] && empty($entry['voidedAt'])) DomainError::fail('INVALID_INPUT', '已有工時的任務不可改成 Epic');
            if ($fields['state'] !== $current['state'] && Fields::category($fields, $project) === 'done') foreach ($epicChildren as $child) if (!in_array(Fields::category($child, $project), ['done', 'canceled'], true)) DomainError::fail('INVALID_INPUT', '還有未結案子任務, 請先完成或取消後再驗收 Epic');
        } elseif ($epicChildren) DomainError::fail('INVALID_INPUT', '請先移轉子任務, 再變更 Epic 類型');
    }

    // 關聯是雙向資料, 所有端點在同一交易更新 revision／history
    private function syncRelations(array $issue, array $previous): void
    {
        foreach (array_unique([...$previous, ...$issue['relatedIssueIds']]) as $id) {
            $peer = $this->store->require('issues', $id);
            $ids = $peer['relatedIssueIds'] ?? [];
            $next = array_values(array_diff($ids, [$issue['id']]));
            if (in_array($id, $issue['relatedIssueIds'], true)) $next[] = $issue['id'];
            sort($next);
            if ($ids !== $next) $this->writeIssue($peer, ['relatedIssueIds' => $next]);
        }
    }

    private function writeIssue(array $current, array $fields): array
    {
        $changes = [];
        foreach ($fields as $field => $value) {
            $before = $current[$field] ?? ($field === 'relatedIssueIds' ? [] : '');
            if ($before !== $value) $changes[] = ['field' => $field, 'before' => $before, 'after' => $value];
        }
        if (!$changes) return $current;
        $issue = [...$current, ...$fields, 'revision' => $current['revision'] + 1, 'updatedAt' => $this->now(), 'history' => [...$current['history'], ['id' => $this->id(), 'kind' => 'updated', 'actor' => $this->context['user']['name'], 'at' => $this->now(), 'changes' => $changes]]];
        $this->store->put('issues', $issue['id'], $issue);
        return $issue;
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
        $issue = $this->writeIssue($current, $fields);
        if ($issue['revision'] === $current['revision']) return $issue;
        $this->syncRelations($issue, $current['relatedIssueIds'] ?? []);
        if ($issue['epicId'] !== $current['epicId']) {
            foreach ($this->store->all('issues') as $child) {
                if (($child['parentIssueId'] ?? '') === $issue['id']) $this->writeIssue($child, ['epicId' => $issue['epicId']]);
            }
        }
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
        if (!$member || $recipientId === $this->context['user']['id'] || !$this->access->forUser($recipientId)->can($issue['projectId'], 'issue.read')) return;
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

    private function loadDemo(array $input): array
    {
        if (in_array('DEMO', array_column($this->store->all('projects'), 'code'), true)) DomainError::fail('DEMO_EXISTS', '示範專案已存在, 不會重複載入');
        $project = $this->createProject(['name' => '產品第一版 · 示範', 'code' => 'DEMO', 'description' => '用這個示範專案試試清單、看板與任務修改, 內容不代表正式需求']);
        $rows = [
            ['整理第一版需求與使用流程', 'done', 'high', 'task'], ['建立專案與任務資料結構', 'done', 'normal', 'task'],
            ['設計任務清單的搜尋與篩選', 'in_progress', 'high', 'task'], ['讓看板拖曳與任務狀態同步', 'in_progress', 'normal', 'task'],
            ['手機上的表單需要更好操作', 'todo', 'high', 'bug'], ['補上任務留言與異動紀錄', 'todo', 'normal', 'task'],
            ['確認 Laravel API 部署方式', 'todo', 'normal', 'task'], ['討論下一次迭代範圍', 'backlog', 'low', 'task'], ['研究適合團隊的登入流程', 'backlog', 'normal', 'task'],
        ];
        foreach ($rows as $index => [$title, $state, $priority, $type]) $this->createIssue(['projectId' => $project['id'], 'title' => $title, 'state' => $state, 'priority' => $priority, 'type' => $type, 'assignee' => in_array($index, [4, 6, 7, 8], true) ? '' : $this->context['user']['id']]);
        return ['projectId' => $project['id']];
    }
}
