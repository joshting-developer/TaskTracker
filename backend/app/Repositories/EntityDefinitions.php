<?php

namespace App\Repositories;

/**
 * 定義舊API欄位與關聯式欄位的轉換；此處不保存業務資料。
 */
class EntityDefinitions
{
    // fields依序為：DTO欄位、SQL欄位、型別、預設值、關聯種類。
    // scope只選擇資料邊界；操作授權仍由集中permission入口判斷。
    public const ENTITIES = [
        'workspaces' => [
            'table' => 'workspaces',
            'model' => 'App\Models\Workspace',
            'scope' => null,
            'fields' => [
                ['name', 'name', 'string', null, null],
                ['createdAt', 'created_at', 'time', null, null],
            ],
        ],
        'members' => [
            'table' => 'workspace_members',
            'model' => 'App\Models\WorkspaceMember',
            'scope' => 'workspace',
            'fields' => [
                ['role', 'role', 'string', 'user', null],
                ['joinedAt', 'joined_at', 'time', null, null],
                ['disabledAt', 'disabled_at', 'time', null, null],
            ],
        ],
        'projects' => [
            'table' => 'projects',
            'model' => 'App\Models\Project',
            'scope' => 'workspace',
            'fields' => [
                ['name', 'name', 'string', null, null],
                ['code', 'code', 'string', null, null],
                ['leadId', 'lead_id', 'ref', null, 'members'],
                ['description', 'description', 'text', '', null],
                ['color', 'color', 'string', '#8d83ed', null],
                ['nextNumber', 'next_number', 'int', 1, null],
                ['revision', 'revision', 'int', 1, null],
                ['createdAt', 'created_at', 'time', null, null],
                ['updatedAt', 'updated_at', 'time', null, null],
            ],
        ],
        'issues' => [
            'table' => 'issues',
            'model' => 'App\Models\Issue',
            'scope' => 'project',
            'fields' => [
                ['projectId', 'project_id', 'ref', null, 'projects'],
                ['identifier', 'identifier', 'string', null, null],
                ['title', 'title', 'string', null, null],
                ['description', 'description', 'text', '', null],
                ['state', 'state_id', 'state', null, null],
                ['priority', 'priority', 'string', 'normal', null],
                ['type', 'type', 'string', 'task', null],
                ['assignee', 'assignee_id', 'ref', null, 'members'],
                ['dueDate', 'due_date', 'date', null, null],
                ['epicId', 'epic_id', 'ref', null, 'issues'],
                ['estimateMinutes', 'estimate_minutes', 'int', 0, null],
                ['parentIssueId', 'parent_issue_id', 'ref', null, 'issues'],
                ['revision', 'revision', 'int', 1, null],
                ['createdAt', 'created_at', 'time', null, null],
                ['updatedAt', 'updated_at', 'time', null, null],
            ],
        ],
        'boards' => [
            'table' => 'boards',
            'model' => 'App\Models\Board',
            'scope' => 'project',
            'fields' => [
                ['projectId', 'project_id', 'ref', null, 'projects'],
                ['name', 'name', 'string', null, null],
                ['revision', 'revision', 'int', 1, null],
                ['createdBy', 'created_by_id', 'ref', null, 'members'],
                ['createdAt', 'created_at', 'time', null, null],
                ['updatedAt', 'updated_at', 'time', null, null],
                ['archivedAt', 'archived_at', 'time', null, null],
            ],
        ],
        'projectMembers' => [
            'table' => 'project_members',
            'model' => 'App\Models\ProjectMember',
            'scope' => 'project',
            'fields' => [
                ['projectId', 'project_id', 'ref', null, 'projects'],
                ['userId', 'member_id', 'ref', null, 'members'],
                ['roleId', 'role_id', 'string', null, null],
                ['revision', 'revision', 'int', 1, null],
                ['removedAt', 'removed_at', 'time', null, null],
                ['updatedAt', 'updated_at', 'time', null, null],
            ],
        ],
        'projectAcls' => [
            'table' => 'project_access_controls',
            'model' => 'App\Models\ProjectAccessControl',
            'scope' => 'project',
            'fields' => [
                ['projectId', 'project_id', 'ref', null, 'projects'],
                ['revision', 'revision', 'int', 0, null],
            ],
        ],
        'timeEntries' => [
            'table' => 'time_entries',
            'model' => 'App\Models\TimeEntry',
            'scope' => 'project',
            'fields' => [
                ['issueId', 'issue_id', 'ref', null, 'issues'],
                ['projectId', 'project_id', 'ref', null, 'projects'],
                ['userId', 'member_id', 'ref', null, 'members'],
                ['createdAt', 'created_at', 'time', null, null],
                ['workDate', 'work_date', 'date', null, null],
                ['minutes', 'minutes', 'int', null, null],
                ['note', 'note', 'text', '', null],
                ['revision', 'revision', 'int', 1, null],
                ['updatedAt', 'updated_at', 'time', null, null],
                ['voidedAt', 'voided_at', 'time', null, null],
            ],
        ],
        'notifications' => [
            'table' => 'issue_notifications',
            'model' => 'App\Models\IssueNotification',
            'scope' => 'project',
            'fields' => [
                ['issueId', 'issue_id', 'ref', null, 'issues'],
                ['projectId', 'project_id', 'ref', null, 'projects'],
                ['recipientId', 'recipient_id', 'ref', null, 'members'],
                ['email', 'email', 'string', null, null],
                ['subject', 'subject', 'string', null, null],
                ['body', 'body', 'text', '', null],
                ['status', 'status', 'string', 'preview', null],
                ['createdAt', 'created_at', 'time', null, null],
            ],
        ],
        'todos' => [
            'table' => 'todos',
            'model' => 'App\Models\Todo',
            'scope' => 'workspace',
            'fields' => [
                ['title', 'title', 'string', null, null],
                ['note', 'note', 'text', '', null],
                ['createdBy', 'created_by_id', 'ref', null, 'members'],
                ['creatorName', 'creator_name', 'string', null, null],
                ['assigneeId', 'assignee_id', 'ref', null, 'members'],
                ['initialAssigneeId', 'initial_assignee_id', 'ref', null, 'members'],
                ['assigneeName', 'assignee_name', 'string', null, null],
                ['state', 'state', 'string', 'open', null],
                ['revision', 'revision', 'int', 1, null],
                ['createdAt', 'created_at', 'time', null, null],
                ['updatedAt', 'updated_at', 'time', null, null],
                ['completedAt', 'completed_at', 'time', null, null],
                ['returnedAt', 'returned_at', 'time', null, null],
                ['convertedIssueId', 'converted_issue_id', 'ref', null, 'issues'],
                ['convertedProjectId', 'converted_project_id', 'ref', null, 'projects'],
            ],
        ],
        'todoNotifications' => [
            'table' => 'todo_notifications',
            'model' => 'App\Models\TodoNotification',
            'scope' => 'workspace',
            'fields' => [
                ['todoId', 'todo_id', 'ref', null, 'todos'],
                ['recipientId', 'recipient_id', 'ref', null, 'members'],
                ['event', 'event', 'string', null, null],
                ['actor', 'actor', 'string', null, null],
                ['createdAt', 'created_at', 'time', null, null],
                ['readAt', 'read_at', 'time', null, null],
            ],
        ],
        'invites' => [
            'table' => 'workspace_invitations',
            'model' => 'App\Models\WorkspaceInvitation',
            'scope' => 'global',
            'fields' => [
                ['workspaceId', 'workspace_id', 'workspace_ref', null, 'workspaces'],
                ['email', 'email', 'string', null, null],
                ['expiresAt', 'expires_at', 'time', null, null],
                ['consumedAt', 'consumed_at', 'time', null, null],
                ['createdAt', 'created_at', 'time', null, null],
            ],
        ],
        'demoSeeds' => [
            'table' => 'demo_seed_states',
            'model' => 'App\Models\DemoSeedState',
            'scope' => 'global',
            'fields' => [
                ['version', 'version', 'int', 1, null],
                ['seededAt', 'seeded_at', 'time', null, null],
                ['upgradedAt', 'upgraded_at', 'time', null, null],
            ],
        ],
        'upgrades' => [
            'table' => 'workspace_upgrades',
            'model' => 'App\Models\WorkspaceUpgrade',
            'scope' => 'workspace',
            'fields' => [
                ['at', 'applied_at', 'time', null, null],
                ['actor', 'actor', 'string', null, null],
                ['note', 'note', 'text', '', null],
                [
                    'roleChanges',
                    'role_changes',
                    'json',
                    [],
                    null,
                ],
            ],
        ],
        'savedViews' => [
            'table' => 'retired_saved_views',
            'model' => 'App\Models\RetiredSavedView',
            'scope' => 'workspace',
            'fields' => [],
        ],
        'requests' => [
            'table' => 'operation_receipts',
            'model' => 'App\Models\OperationReceipt',
            'scope' => 'workspace',
            'fields' => [
                ['signature', 'signature', 'string', null, null],
                ['result', 'result', 'json', null, null],
            ],
        ],
        'todoRequests' => [
            'table' => 'todo_operation_receipts',
            'model' => 'App\Models\TodoOperationReceipt',
            'scope' => 'workspace',
            'fields' => [
                ['signature', 'signature', 'string', null, null],
                ['result', 'result', 'json', null, null],
            ],
        ],
    ];
}
