-- 僅供隔離測試Compose；應用程式正式帳號沒有這個資料庫的權限。
CREATE DATABASE tasktracker_test_import CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
GRANT ALL PRIVILEGES ON tasktracker_test_import.* TO 'tasktracker_test'@'%';
