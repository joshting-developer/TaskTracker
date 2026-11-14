# TT-326 既有帳號建立工作空間

登入後從左側工作空間選單按「建立工作空間」, 輸入名稱即可建立。一般成員也能使用, 不需要再註冊另一個Email或輸入密碼

新空間的Owner是目前帳號, 建立後切換到新空間；原空間的角色、專案權限及資料不改。舊帳號保留原有登入資格, 需驗證但尚未啟用的新帳號仍不能繞過信箱驗證

## 契約與保存

POST `/api/auth/createWorkspace`, 沿用Session及CSRF, 不提供PAT工具專用路徑。接受workspaceName（1～60字）及UUID requestId, 每IP每分鐘5次；正式機器契約為Session OpenAPI 1.6.0

AccountSessionBoundary是交易owner, 先取得User鎖並重驗登入版本, 再呼叫WorkspaceCreationService → WorkspaceCreationRepository。新空間、Owner資格與請求紀錄一起提交, 失敗全部回滾

workspace_creation_requests以User＋requestId唯一保存名稱雜湊及新空間指標。同請求重試只回原結果, 不同名稱沿用同識別碼得到409。重播前仍查本人資格, 空間已刪除得到404, 撤權得到403, 不重新建立空間或補回角色。空間外鍵採null-on-delete保留已使用請求的紀錄

只取目前帳號、單一receipt及對應新空間, 不載入其他空間資料或任務／留言／歷史／工時。前端按提交才送出, 保留同名稱失敗重試的requestId, 輸入改名後才產生新識別碼

## 驗證與發布

SQLite及隔離MySQL覆蓋一般成員、原角色保存、同請求重播、名稱衝突、撤權／刪除、未驗證／舊登入、輸入上限、限流、交易回滾與資料讀取範圍

Chrome以合成受邀成員實測三尺寸, 包含第一次已建立但回應遺失後重試, 新空間仍只有一個、同帳號成為Owner, 並能切回原空間的一般成員身分

migration只在本地隔離測試執行, 正式站等整批tag時先migration再更新程式。沒有額外線上測試站
