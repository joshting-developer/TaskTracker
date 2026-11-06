<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Domain\DomainError;
use App\Domain\Fields;
use App\Domain\RecordRevision;
use App\Repositories\RecordStore;
use Illuminate\Support\Str;

/**
 * 管理專案資訊與自訂狀態，保留版本檢查及修改歷史。
 */
class ProjectService
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
     * 驗證代碼與負責人後建立專案。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function createProject(array $input): array
    {
        $name = Fields::text($input['name'] ?? null, '專案名稱', 60, true);
        $code = strtoupper(Fields::text($input['code'] ?? null, '專案代碼', 8, true));
        if (! preg_match('/^[A-Z][A-Z0-9]{1,7}$/D', $code)) {
            DomainError::fail('INVALID_INPUT', '專案代碼需為 2–8 位英文字母或數字, 並以英文字母開頭');
        }
        $projects = $this->store->all('projects');
        if (in_array($code, array_column($projects, 'code'), true)) {
            DomainError::fail('DUPLICATE_CODE', '這個專案代碼已使用');
        }
        $leadId = $this->projectLead($input['leadId'] ?? $this->workspaceSession['user']['id']);
        $project = [
            'id' => $this->id(),
            'name' => $name,
            'code' => $code,
            'leadId' => $leadId,
            'description' => Fields::text($input['description'] ?? '', '專案說明', 500),
            'color' => ['#8d83ed', '#64b0a0', '#e5a25c', '#cf82ae', '#71a2d5'][count($projects) % 5],
            'nextNumber' => 1,
            'revision' => 1,
            'statuses' => Fields::STATES,
            'settingsHistory' => [],
            'createdAt' => $this->now(),
        ];
        $this->store->put('projects', $project['id'], $project);
        if ($leadId && ! $this->access->forUser($leadId)->can($project['id'], 'project.read')) {
            DomainError::fail('INVALID_INPUT', '請先將一般成員加入專案, 再指派為專案負責人');
        }

        return $project;
    }

    /**
     * 檢查負責人的成員資格與專案存取權。
     *
     * @param  mixed  $value  待驗證或正規化的值
     * @param  ?string  $projectId  所屬專案識別碼
     * @return string
     */
    private function projectLead(mixed $value, ?string $projectId = null): string
    {
        $id = Fields::text($value, '專案負責人', 100);
        if ($id !== '') {
            $member = $this->store->get('members', $id);
            if (! $member || ! empty($member['disabledAt']) || $projectId && ! $this->access->forUser($id)->can($projectId, 'project.read')) {
                DomainError::fail('INVALID_INPUT', '專案負責人必須是可存取目前專案的有效成員');
            }
        }

        return $id;
    }

    /**
     * 依版本更新專案介紹與責任資訊並保存歷史。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function updateProject(array $input): array
    {
        $project = $this->store->require('projects', $input['id'] ?? '');
        RecordRevision::assertCurrent($project, $input, '專案');
        if (isset($input['code']) && $input['code'] !== $project['code']) {
            DomainError::fail('INVALID_INPUT', '專案代碼建立後不可修改');
        }
        $fields = [
            'name' => Fields::text($input['name'] ?? $project['name'], '專案名稱', 60, true),
            'description' => Fields::text($input['description'] ?? $project['description'] ?? '', '專案介紹', 500),
            'leadId' => $this->projectLead($input['leadId'] ?? $project['leadId'] ?? '', isset($input['leadId']) && $input['leadId'] !== ($project['leadId'] ?? '') ? $project['id'] : null),
        ];
        $changes = [];
        foreach ($fields as $field => $value) {
            if (($project[$field] ?? '') !== $value) {
                $changes[] = [
                    'field' => $field,
                    'before' => $project[$field] ?? '',
                    'after' => $value,
                ];
            }
        }
        if (! $changes) {
            return $project;
        }
        $next = [
            ...$project,
            ...$fields,
            'revision' => $project['revision'] + 1,
            'updatedAt' => $this->now(),
            'detailsHistory' => [
                ...$project['detailsHistory'] ?? [],
                [
                    'actor' => $this->workspaceSession['user']['name'],
                    'at' => $this->now(),
                    'changes' => $changes,
                ],
            ],
        ];
        $this->store->put('projects', $next['id'], $next);

        return $next;
    }

    /**
     * 驗證狀態集合及使用中狀態後更新專案設定。
     *
     * @param  array  $input  此次操作的原始輸入欄位
     * @return array
     */
    public function updateStatuses(array $input): array
    {
        $project = $this->store->require('projects', $input['projectId'] ?? '');
        RecordRevision::assertCurrent($project, $input, '專案設定');
        $states = $input['statuses'] ?? null;
        if (! is_array($states) || ! array_is_list($states) || count($states) < 2 || count($states) > 20) {
            DomainError::fail('INVALID_INPUT', '請設定 2–20 個狀態');
        }
        $states = array_map(function ($s) {
            if (! is_array($s)) {
                DomainError::fail('INVALID_INPUT', '狀態格式不正確');
            }
            if (array_key_exists('category', $s)) {
                DomainError::fail('INVALID_INPUT', '狀態分類已停用，請重新整理後使用 type 與顯示名稱');
            }
            $id = Fields::text($s['id'] ?? null, '狀態 type', 100, true);
            $color = Fields::text($s['color'] ?? null, '顏色', 7, true);
            if (! preg_match('/^[A-Za-z0-9_-]+$/D', $id) || ! preg_match('/^#[0-9a-f]{6}$/iD', $color)) {
                DomainError::fail('INVALID_INPUT', '狀態 ID 或顏色不正確');
            }

            return [
                'id' => $id,
                'label' => Fields::text($s['label'] ?? null, '狀態名稱', 30, true),
                'color' => $color,
            ];
        }, $states);
        if (count(array_unique(array_column($states, 'id'))) !== count($states) || count(array_unique(array_column($states, 'label'))) !== count($states)) {
            DomainError::fail('INVALID_INPUT', '狀態 ID 與名稱不可重複');
        }
        if (array_diff(['open', 'closed'], array_column($states, 'id'))) {
            DomainError::fail('INVALID_INPUT', 'open 與 closed 狀態不可移除');
        }
        $existingIds = array_column($project['statuses'] ?? Fields::STATES, 'id');
        foreach ($states as $state) {
            if (! in_array($state['id'], $existingIds, true) && ! preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $state['id'])) {
                DomainError::fail('INVALID_INPUT', '新狀態 type 須以小寫英文字母開頭，最多 40 字，只能包含小寫英文、數字與底線');
            }
        }
        foreach ($this->store->all('issues') as $issue) {
            if ($issue['projectId'] === $project['id'] && ! in_array($issue['state'], array_column($states, 'id'), true)) {
                DomainError::fail('INVALID_INPUT', '使用中的狀態不能移除, 請先移轉任務');
            }
        }
        $next = [
            ...$project,
            'statuses' => $states,
            'revision' => $project['revision'] + 1,
            'settingsHistory' => [
                ...$project['settingsHistory'] ?? [],
                [
                    'actor' => $this->workspaceSession['user']['name'],
                    'at' => $this->now(),
                    'before' => $project['statuses'] ?? Fields::STATES,
                    'after' => $states,
                ],
            ],
        ];
        $this->store->put('projects', $project['id'], $next);

        return $next;
    }
}
