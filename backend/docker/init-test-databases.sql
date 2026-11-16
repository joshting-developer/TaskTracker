-- Additional schema only for an explicitly selected standalone test instance.
SET @is_test_instance = DATABASE() = 'tasktracker_test'
    AND EXISTS(SELECT 1 FROM mysql.user WHERE User = 'tasktracker_test');
SET @create_import = IF(@is_test_instance,
    'CREATE DATABASE IF NOT EXISTS `tasktracker_test_import` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
    'SELECT 1');
PREPARE create_import FROM @create_import;
EXECUTE create_import;
DEALLOCATE PREPARE create_import;
SET @grant_import = IF(@is_test_instance,
    'GRANT ALL PRIVILEGES ON `tasktracker\\_test\\_import`.* TO ''tasktracker_test''@''%''',
    'SELECT 1');
PREPARE grant_import FROM @grant_import;
EXECUTE grant_import;
DEALLOCATE PREPARE grant_import;
