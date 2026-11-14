# TT-330 正式MySQL最小權限

Josh明確授權由Codex承接, 保留既有管理帳號與密碼

## 設計
- runtime只授權task_tracker的SELECT／INSERT／UPDATE／DELETE, REQUIRE SSL
- migration另帳號, 只授權相同schema的DML及CREATE／ALTER／DROP／INDEX／REFERENCES等實際必要DDL, 無全域管理／GRANT
- migration密碼只存CI production Secret, 不放Fly App共享Secrets, HTTP不能讀到
- 使用同一受測版本映像的一次性Fly Machine執行migration, 無HTTP服務、無自動重啟, 完成／失敗清理；CI在migration成功後才部署HTTP
- runtime帳號與migration帳號先隔離實測, 正式新增帳號不修改業務資料, 不撤銷原管理帳號
- 正式runtime憑證切換與回復另驗, 不能只建立帳號就稱已收斂

## 驗證
隔離MySQL驗runtime DML成功／DDL與跨庫拒絕, migration DDL成功／跨庫及GRANT拒絕；單次Machine API編排成功、失敗、超時清理測試, 發布前置順序檢查
正式只驗連線及SHOW GRANTS, 不建立測試業務資料或試建管理帳號
