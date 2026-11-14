# TT-358 無有效工作區的帳號

## 確認行為
帳號可在沒有有效工作區時正常登入, 左上角顯示「無隸屬工作區」。首頁「建立工作區」開啟同一個WorkspaceSwitcher彈窗, 再由內部建立入口填名稱；一般成員同樣可建立, 不需要Owner資格

## 邊界
帳號Session與WorkspaceContext分開。current可回workspace:null及空permissions, Workspace API仍要求有效資格。舊scope失效後清除畫面／路由／請求與快取, 不將失去最後資格當密碼錯誤。保留舊登入版本與Email驗證政策

個人資料與登出是帳號能力, 不依賴Workspace；保留原RPC相容入口, 新帳號路徑由相同HTTP帳號交易取得User鎖、重驗Session, 個人更新有revision與requestId保護

## 驗證
SQLite／MySQL覆蓋零空間登入、最後資格失效、其他資格切換、Workspace API拒絕、profile／建立與重播、舊Session及未驗證拒絕。真Chrome三寬度測空首頁→同一切換彈窗→建立→Owner, 並確認沒有無權Workspace背景呼叫；三尺寸視覺與登入完整回歸
