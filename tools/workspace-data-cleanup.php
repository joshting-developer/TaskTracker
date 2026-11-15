<?php

use Pdo\Mysql;

/** One-off maintenance: exact reviewed manifest, transaction, FK protection and rollback by default. */
if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('CLI only');
}
$manifestPath = $argv[1] ?? '';
$connectionPath = $argv[2] ?? '';
foreach ([$manifestPath, $connectionPath] as $path) {
    if (! is_file($path) || is_link($path) || (fileperms($path) & 0077)) {
        throw new RuntimeException('Private regular manifest and connection files required');
    }
}
$plan = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
$conn = json_decode(file_get_contents($connectionPath), true, flags: JSON_THROW_ON_ERROR);
$apply = ($argv[3] ?? '') === '--apply';
if (! in_array($argv[3] ?? '', ['', '--apply'], true) || ($plan['approval'] ?? '') !== 'TT-361-exact-scope') {
    throw new RuntimeException('Reviewed scope required');
}
if ($apply && (! is_file($plan['backupPath'] ?? '') || ! hash_equals($plan['backupSha256'] ?? '', hash_file('sha256', $plan['backupPath'])))) {
    throw new RuntimeException('Verified backup required before applying');
}
$db = new PDO($conn['dsn'], $conn['username'], $conn['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false] + (isset($conn['ca']) ? [Mysql::ATTR_SSL_CA => $conn['ca'], Mysql::ATTR_SSL_VERIFY_SERVER_CERT => true] : []));
$fetch = function (string $sql, array $params = []) use ($db): array {
    $q = $db->prepare($sql);
    $q->execute($params);

    return $q->fetchAll(PDO::FETCH_ASSOC);
};
$changes = [];
$write = function (string $sql, array $params = []) use ($db, &$changes): void {
    $q = $db->prepare($sql);
    $q->execute($params);
    $changes[] = ['operation' => explode(' ', $sql, 3)[0], 'rows' => $q->rowCount()];
};
$quote = function (string $name): string {
    if (! preg_match('/^[a-z_]+$/D', $name)) {
        throw new RuntimeException('Unexpected identifier');
    }

    return '`'.$name.'`';
};
$db->beginTransaction();
try {
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== $plan['database']) {
        throw new RuntimeException('Database mismatch');
    }
    $workspace = $fetch('SELECT id, public_id, name FROM workspaces WHERE id=? FOR UPDATE', [$plan['workspaceId']]);
    if (count($workspace) !== 1 || $workspace[0]['public_id'] !== $plan['workspacePublicId'] || $workspace[0]['name'] !== $plan['workspaceName']) {
        throw new RuntimeException('Workspace mismatch');
    }
    $workspaceIds = array_column($fetch('SELECT id,public_id FROM workspaces ORDER BY id FOR UPDATE'), 'public_id', 'id');
    if ($workspaceIds != $plan['workspaceInventory']) {
        throw new RuntimeException('Workspace inventory changed; review again');
    }
    $keep = array_keys($plan['keepUsers']);
    $users = $fetch('SELECT id,name,email FROM users ORDER BY id FOR UPDATE');
    $actual = array_column($users, 'name', 'id');
    foreach ($plan['keepUsers'] as $id => $name) {
        if (($actual[$id] ?? null) !== $name) {
            throw new RuntimeException('Retained account mismatch');
        }
    }
    $remove = array_values(array_diff(array_keys($actual), $keep));
    if (array_diff($remove, $plan['removeUsers']) || array_diff($plan['removeUsers'], $remove)) {
        throw new RuntimeException('Account inventory changed; review again');
    }
    $members = $fetch('SELECT id,user_id,role FROM workspace_members WHERE workspace_id=? FOR UPDATE', [$plan['workspaceId']]);
    $memberIds = array_column($members, 'id', 'user_id');
    foreach ($keep as $id) {
        if (! isset($memberIds[$id])) {
            throw new RuntimeException('Retained account lacks membership');
        }
    }
    $targetUser = $plan['transferTo'];
    if (! in_array($targetUser, $keep, true)) {
        throw new RuntimeException('Invalid transfer target');
    }
    $targetMember = $memberIds[$targetUser];
    $targetName = $actual[$targetUser];
    $metadata = $fetch('SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION');
    $columns = [];
    foreach ($metadata as $row) {
        $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $row;
    }
    $foreign = $fetch('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL');
    $keepWorkspace = (int) $plan['workspaceId'];
    $keepBefore = [];
    foreach (['projects', 'issues', 'boards', 'time_entries', 'todos', 'attachments'] as $table) {
        $keepBefore[$table] = (int) $db->query("SELECT COUNT(*) FROM `$table` WHERE workspace_id=$keepWorkspace")->fetchColumn();
    }
    $commentBefore = (int) $db->query("SELECT COUNT(*) FROM issue_comments c JOIN issues i ON i.id=c.issue_id WHERE i.workspace_id=$keepWorkspace")->fetchColumn();
    $uuidMap = $nameMap = [];
    foreach ($plan['transferUsers'] as $old) {
        if (! in_array($old, $remove, true) || ! isset($memberIds[$old])) {
            throw new RuntimeException('Invalid old AI membership');
        }
        $oldMember = $memberIds[$old];
        $uuidMap[$old] = $targetUser;
        $emailById = array_column($users, 'email', 'id');
        $uuidMap[$emailById[$old]] = $emailById[$targetUser];
        $nameMap[$actual[$old]] = $targetName;
        // Existing target ACL wins only when it is the same grant; never silently elevate a role.
        foreach ($fetch('SELECT * FROM project_members WHERE workspace_id=? AND member_id=?', [$keepWorkspace, $oldMember]) as $acl) {
            $existing = $fetch('SELECT id,role_id,removed_at FROM project_members WHERE project_id=? AND member_id=?', [$acl['project_id'], $targetMember]);
            if ($existing) {
                if ($existing[0]['role_id'] !== $acl['role_id'] || $existing[0]['removed_at'] !== $acl['removed_at']) {
                    throw new RuntimeException('Conflicting ACL requires review');
                }
                $write('DELETE FROM project_members WHERE id=?', [$acl['id']]);
            }
        }
        $write('DELETE p FROM todo_participants p JOIN todo_participants t ON t.todo_id=p.todo_id AND t.member_id=? WHERE p.workspace_id=? AND p.member_id=?', [$targetMember, $keepWorkspace, $oldMember]);
        foreach ($foreign as $fk) {
            if ($fk['REFERENCED_TABLE_NAME'] !== 'workspace_members' || $fk['REFERENCED_COLUMN_NAME'] !== 'id') {
                continue;
            }
            $table = $quote($fk['TABLE_NAME']);
            $column = $quote($fk['COLUMN_NAME']);
            $write("UPDATE $table SET $column=? WHERE workspace_id=? AND $column=?", [$targetMember, $keepWorkspace, $oldMember]);
        }
        foreach (['todos' => ['creator_name' => 'created_by_id', 'assignee_name' => 'assignee_id'], 'todo_handoffs' => ['from_name' => 'from_id', 'to_name' => 'to_id']] as $table => $names) {
            foreach ($names as $name => $idColumn) {
                $write("UPDATE `$table` SET `$name`=? WHERE workspace_id=? AND `$idColumn`=? AND `$name`=?", [$targetName, $keepWorkspace, $targetMember, $actual[$old]]);
            }
        }
        $write('UPDATE issue_comments c JOIN issues i ON i.id=c.issue_id SET c.author=? WHERE i.workspace_id=? AND c.author=?', [$targetName, $keepWorkspace, $actual[$old]]);
        $write('UPDATE integration_audits SET actor_user_id=?,token_id=NULL,token_name=NULL WHERE workspace_id=? AND actor_user_id=?', [$targetUser, $keepWorkspace, $old]);
        $write('UPDATE http_operation_logs SET user_id=?,token_id=NULL WHERE workspace_public_id=? AND user_id=?', [$targetUser, $plan['workspacePublicId'], $old]);
        foreach ($foreign as $fk) {
            if ($fk['REFERENCED_TABLE_NAME'] !== 'users' || $fk['TABLE_NAME'] === 'workspace_members' || $fk['TABLE_NAME'] === 'personal_access_tokens' || $fk['TABLE_NAME'] === 'mail_deliveries' || $fk['TABLE_NAME'] === 'email_verifications') {
                continue;
            }
            $table = $fk['TABLE_NAME'];
            $column = $fk['COLUMN_NAME'];
            if (isset($columns[$table]['workspace_id'])) {
                $write("UPDATE `$table` SET `$column`=? WHERE workspace_id=? AND `$column`=?", [$targetUser, $keepWorkspace, $old]);
            }
        }
    }
    // Removed identities cannot replay requests; discard their receipts before scanning retained JSON.
    foreach ($remove as $id) {
        foreach (['operation_receipts', 'todo_operation_receipts'] as $table) {
            $write("DELETE FROM `$table` WHERE public_id LIKE ?", [$id.':%']);
        }
    }
    // Repoint structured references, not arbitrary user-authored prose or opaque receipt signatures.
    $rewrite = function ($value, ?string $field = null) use (&$rewrite, $uuidMap, $nameMap) {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $rewrite($v, (string) $k);
            }

            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }
        $identityNameFields = ['actor', 'author', 'actorName', 'authorName', 'creatorName', 'createdByName', 'assigneeName', 'initialAssigneeName', 'fromName', 'toName', 'memberName', 'userName', 'leadName', 'uploadedByName'];

        return $uuidMap[$value] ?? (in_array($field, $identityNameFields, true) ? ($nameMap[$value] ?? $value) : $value);
    };
    foreach ($columns as $table => $fields) {
        if (! isset($fields['workspace_id']) || ! isset($fields['id'])) {
            continue;
        }
        foreach ($fields as $column => $info) {
            if ($info['DATA_TYPE'] !== 'json') {
                continue;
            }
            foreach ($fetch("SELECT id,`$column` AS payload FROM `$table` WHERE workspace_id=? AND `$column` IS NOT NULL", [$keepWorkspace]) as $row) {
                $old = json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR);
                $new = $rewrite($old);
                if ($new !== $old) {
                    $write("UPDATE `$table` SET `$column`=? WHERE id=? AND workspace_id=?", [json_encode($new, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $row['id'], $keepWorkspace]);
                }
            }
        }
    }
    $removedMembers = array_values(array_diff(array_column($members, 'id'), array_intersect_key($memberIds, array_flip($keep))));
    foreach ($removedMembers as $id) {
        foreach ($foreign as $fk) {
            if ($fk['REFERENCED_TABLE_NAME'] !== 'workspace_members' || $fk['REFERENCED_COLUMN_NAME'] !== 'id') {
                continue;
            }
            $table = $fk['TABLE_NAME'];
            $column = $fk['COLUMN_NAME'];
            $count = (int) $fetch("SELECT COUNT(*) AS n FROM `$table` WHERE workspace_id=? AND `$column`=?", [$keepWorkspace, $id])[0]['n'];
            if ($count) {
                throw new RuntimeException('Unmapped retained member references in '.$table);
            }
        }
    }
    $removeEmails = array_column(array_filter($users, fn ($u) => in_array($u['id'], $remove, true)), 'email');
    foreach ($remove as $id) {
        foreach (['personal_access_tokens' => 'tokenable_id', 'sessions' => 'user_id', 'email_verifications' => 'user_id', 'mail_deliveries' => 'user_id'] as $table => $column) {
            $write("DELETE FROM `$table` WHERE `$column`=?", [$id]);
        }
        $write('DELETE FROM http_operation_logs WHERE user_id=?', [$id]);
        $write('DELETE FROM integration_audits WHERE actor_user_id=?', [$id]);
    }
    foreach ($removeEmails as $email) {
        $write('DELETE m FROM mail_deliveries m JOIN workspace_invitations i ON i.id=m.invitation_id WHERE i.email=?', [$email]);
        $write('DELETE FROM workspace_invitations WHERE email=?', [$email]);
        $write('DELETE FROM password_reset_tokens WHERE email=?', [$email]);
    }
    // Archive records contain only the removed demo workspace; verify before dropping that legacy copy.
    foreach ($fetch('SELECT kind,id FROM records') as $row) {
        if (! str_starts_with($row['kind'], 'w:tasktracker-seed-f-ws:') && ! ($row['kind'] === 'workspaces' && $row['id'] === 'tasktracker-seed-f-ws') && ! ($row['kind'] === 'demoSeeds' && $row['id'] === 'tasktracker-seed-f-ws')) {
            throw new RuntimeException('Unknown legacy archive scope');
        }
    }
    $write('DELETE FROM records');
    $write('DELETE m FROM mail_deliveries m JOIN workspace_invitations i ON i.id=m.invitation_id WHERE i.workspace_id<>?', [$keepWorkspace]);
    $write('DELETE c FROM issue_comments c JOIN issues i ON i.id=c.issue_id WHERE i.workspace_id<>?', [$keepWorkspace]);
    $write('UPDATE issues SET epic_id=NULL,parent_issue_id=NULL WHERE workspace_id<>?', [$keepWorkspace]);
    $write('UPDATE todos SET converted_issue_id=NULL,converted_project_id=NULL WHERE workspace_id<>?', [$keepWorkspace]);
    // Children first, keeping MySQL FK checks active throughout.
    $tables = array_keys(array_filter($columns, fn ($fields) => isset($fields['workspace_id'])));
    $ordered = [];
    $pending = array_values(array_diff($tables, ['workspaces']));
    while ($pending) {
        $progress = false;
        foreach ($pending as $index => $table) {
            $children = array_filter($foreign, fn ($f) => $f['REFERENCED_TABLE_NAME'] === $table && $f['TABLE_NAME'] !== $table && in_array($f['TABLE_NAME'], $pending, true));
            if ($children) {
                continue;
            }
            $ordered[] = $table;
            unset($pending[$index]);
            $progress = true;
        }
        if (! $progress) {
            throw new RuntimeException('Unexpected foreign-key cycle');
        }
        $pending = array_values($pending);
    }
    foreach ($ordered as $table) {
        if ($table === 'workspace_members') {
            foreach ($removedMembers as $id) {
                $write('DELETE FROM workspace_members WHERE workspace_id=? AND id=?', [$keepWorkspace, $id]);
            }
        }
        $write("DELETE FROM `$table` WHERE workspace_id<>?", [$keepWorkspace]);
    }
    $write('DELETE FROM http_operation_logs WHERE workspace_public_id IS NOT NULL AND workspace_public_id<>?', [$plan['workspacePublicId']]);
    $write('DELETE FROM workspaces WHERE id<>?', [$keepWorkspace]);
    foreach ($remove as $id) {
        $write('DELETE FROM users WHERE id=?', [$id]);
    }
    $remaining = array_column($fetch('SELECT id FROM users'), 'id');
    if (array_diff($remaining, $keep) || array_diff($keep, $remaining)) {
        throw new RuntimeException('Final accounts mismatch');
    }
    if ((int) $db->query('SELECT COUNT(*) FROM workspaces')->fetchColumn() !== 1) {
        throw new RuntimeException('Final workspace mismatch');
    }
    foreach ($keepBefore as $table => $count) {
        if ((int) $db->query("SELECT COUNT(*) FROM `$table` WHERE workspace_id=$keepWorkspace")->fetchColumn() !== $count) {
            throw new RuntimeException('Retained data count changed: '.$table);
        }
    }
    if ((int) $db->query('SELECT COUNT(*) FROM issue_comments')->fetchColumn() !== $commentBefore) {
        throw new RuntimeException('Retained comments changed');
    }
    $result = ['applied' => $apply, 'workspaceCount' => 1, 'accountCount' => count($keep), 'removedAccounts' => count($remove), 'retained' => $keepBefore, 'retainedComments' => $commentBefore, 'changedRows' => array_sum(array_column($changes, 'rows')), 'foreignKeyChecks' => (int) $db->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn()];
    if ($result['foreignKeyChecks'] !== 1) {
        throw new RuntimeException('Foreign keys disabled');
    }
    $apply ? $db->commit() : $db->rollBack();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $failure) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Cleanup refused: '.$failure->getMessage().PHP_EOL);
    exit(1);
}
