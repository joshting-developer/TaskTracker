<?php

namespace App\Services;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspacePermissions as Permissions;
use App\Repositories\RecordStore;

/**
 * 彙整目前操作者有權讀取的工作空間資料與能力資訊。
 */
class WorkspaceQuery
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
     * 只回傳目前操作者可存取的專案及其衍生資料。
     *
     * @return array
     */
    public function getWorkspace(): array
    {
        $projects = [];
        $memberIds = [$this->workspaceSession['user']['id']];
        foreach ($this->store->all('projects') as $project) {
            if (! $this->access->can($project['id'], 'project.read')) {
                continue;
            }
            $eligible = array_column($this->access->eligibleMembers($project['id']), 'id');
            $memberIds = [...$memberIds, ...$eligible];
            // capabilities 是此操作者目前的 scope 結果, 不存回原 Project
            $projects[] = [
                ...$project,
                'permissions' => $this->access->permissions($project['id']),
                'accessSource' => $this->access->source($project['id']),
                'memberIds' => $eligible,
            ];
        }
        $issueProjects = array_column(array_filter($projects, fn ($project) => $this->access->can($project['id'], 'issue.read')), 'id');
        $issues = $this->visibleIssues($issueProjects);
        $issueIds = array_column($issues, 'id');
        $canReadAllMembers = in_array('workspace.members.read', $this->workspaceSession['permissions'], true);
        $boards = (new ProjectBoards($this->store, $this->workspaceSession))->listing();

        return [
            'projects' => $projects,
            'issues' => $issues,
            'boards' => array_values(array_filter($boards, fn ($board) => in_array($board['projectId'], $issueProjects, true))),
            'members' => array_values(array_filter($this->store->all('members'), fn ($member) => $canReadAllMembers || in_array($member['id'], $memberIds, true))),
            'timeEntries' => $this->visibleTimeEntries($issueIds),
            'notifications' => $this->visibleNotifications($issueIds),
            'meta' => $this->metadata(),
        ];
    }

    /**
     * 取得可讀專案的任務並維持原有排序。
     *
     * @param  array  $projectIds  目前操作者可讀的專案識別碼
     * @return array
     */
    private function visibleIssues(array $projectIds): array
    {
        $issues = array_values(array_filter($this->store->all('issues'), fn ($issue) => in_array($issue['projectId'], $projectIds, true)));
        usort($issues, fn ($first, $second) => strcmp($second['updatedAt'], $first['updatedAt']) ?: strcmp($first['identifier'], $second['identifier']));

        return $issues;
    }

    /**
     * 依任務可見性、團隊工時與本人權限篩選紀錄。
     *
     * @param  array  $issueIds  目前操作者可見的任務識別碼
     * @return array
     */
    private function visibleTimeEntries(array $issueIds): array
    {
        return array_values(array_filter($this->store->all('timeEntries'), function ($entry) use ($issueIds) {
            if (! in_array($entry['issueId'], $issueIds, true)) {
                return false;
            }
            $canReadTeam = $this->access->can($entry['projectId'], 'timeEntry.readTeam');
            $isOwnEntry = $entry['userId'] === $this->workspaceSession['user']['id'];
            $canReadOwn = $isOwnEntry && $this->access->can($entry['projectId'], 'timeEntry.readOwn');

            return $canReadTeam || $canReadOwn;
        }));
    }

    /**
     * 只回傳可見任務的團隊通知或本人收件。
     *
     * @param  array  $issueIds  目前操作者可見的任務識別碼
     * @return array
     */
    private function visibleNotifications(array $issueIds): array
    {
        return array_values(array_filter($this->store->all('notifications'), function ($notification) use ($issueIds) {
            if (! in_array($notification['issueId'], $issueIds, true)) {
                return false;
            }
            $canReadTeam = $this->access->can($notification['projectId'], 'notification.readTeam');
            $isRecipient = $notification['recipientId'] === $this->workspaceSession['user']['id'];

            return $canReadTeam || $isRecipient;
        }));
    }

    /**
     * 整理目前工作空間身分與可指派負責人。
     *
     * @return array
     */
    private function metadata(): array
    {
        return [
            'mode' => 'local',
            'actor' => $this->workspaceSession['user']['name'],
            'userId' => $this->workspaceSession['user']['id'],
            'role' => $this->workspaceSession['role'],
            'permissions' => $this->workspaceSession['permissions'],
            'workspace' => $this->workspaceSession['workspace'],
            'singleUser' => false,
            'projectLeadIds' => $this->projectLeadIds(),
        ];
    }

    /**
     * 列出可作新專案負責人的有效成員。
     *
     * @return array
     */
    private function projectLeadIds(): array
    {
        if (! in_array('project.create', $this->workspaceSession['permissions'], true)) {
            return [];
        }

        return array_column(array_filter($this->store->all('members'), function ($member) {
            if (! empty($member['disabledAt'])) {
                return false;
            }

            return in_array('project.readAny', Permissions::resolve($member['role']), true);
        }), 'id');
    }
}
