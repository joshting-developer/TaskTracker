# TT-326 既有帳號建立工作空間

## 行為
在工作空間切換視窗提供建立入口, 一般成員也能沿用帳號建立, 建立後切換至新空間並成為Owner, 原空間角色與專案權限不變

## 後端
沿Session／CSRF的AccountSessionBoundary, 先取得User鎖再重驗登入版本與信箱資格。WorkspaceCreationService → WorkspaceCreationRepository, 不依目前Workspace角色決定全域帳號能力

以User＋requestId唯一的workspace_creation_requests保存建立結果及名稱簽章；相同請求只建立一次, 不同名稱同識別碼衝突。重播須重驗新空間仍可存取, 已刪除空間保留空receipt不重建

## 驗證
SQLite／隔離MySQL驗一般成員、Owner、原角色與資料隔離、錯名稱／匿名／未驗證／舊登入、重播／衝突／撤權及資料讀取範圍。Chrome三寬度從切換視窗建立、返回原空間, 無第二個帳號；契約／視覺／冒煙與完整登入回歸同步
