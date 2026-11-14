# TT-340 邀請信與待接受邀請

Josh在TT-185驗收紙條指出, 邀請應寄Email, 並能查閱還沒接受的人。本次保留原連結接受方式, 加上寄信與獨立待接受清單

## 產品行為

- 工作空間設定 → 成員與權限 → 邀請成員, 輸入Email後建立邀請並進入既有寄信outbox
- 新Email或已註冊帳號均可邀請。建立後立即出現在「待接受邀請」, 顯示Email、預計加入身分、期限與寄送狀態；不查全平台帳號姓名
- 對方接受後從待接受清單移除, 正式成員目錄可查到對方；接受前不建立有效成員或專案權限
- 連結有效7天, 同Email重新建立會讓舊連結到期；已接受、過期不再出現在待接受清單
- 保留複製連結作為備用。信件待寄、失敗或結果不明都如實顯示, 不以log mailer冒稱已實寄
- 網路回應遺失時沿用同一requestId重試, 不再建立或寄送第二份。Token結果以加密receipt保存, 不把原始token存進明碼業務紀錄
- 寄送時重驗當次邀請與邀請者的workspace.invite資格, 已接受、到期或撤權則略過；SMTP預連線後也會再驗。結果不明不自動補寄

## 介面與分層

正式機器契約在backend/resources/auth-session-openapi.json, Session＋CSRF寫入沿既有POST /api/rpc/inviteMember；待接受讀取為GET /api/v1/workspace/invitations。這不是PAT公開端點, 沒有新增工具專用流程

HTTP → WorkspaceInvitationService → WorkspaceInvitationRepository。MailDeliveryService與其Repository處理既有寄信狀態機, WorkspaceRpc既有交易為建立邀請的transaction owner；寄信只在commit後進行

前端WorkspaceInvitationManager擁有地址草稿、requestId、連結與邀請分頁, WorkspaceSettings只協調分區、離頁草稿與刷新。沒有新增全域狀態或定期輪詢

## 資料存取八項審查

1. 邀請分區只需id、email、role、createdAt、expiresAt、deliveryStatus；一般設定、正式成員與角色查詢各自維持獨立, 不額外附帶邀請資料
2. 清單預設20／上限100筆, expires_at DESC、id DESC穩定排序, 成員／邀請各自分頁
3. Workspace、consumed_at、expires_at與地址搜尋在SQL執行；查詢只選安全狀態欄位, 不解密信件、不讀receipt或token
4. 不載整個Workspace, 不預載所有帳號或邀請歷史
5. 不讀卡片、留言、歷史或工時；邀請接受沿原HTTP流程與Email比對
6. 進入成員分區、邀請成功及手動刷新才載入, 沒有背景輪詢。資料量增加時COUNT仍需計算符合範圍總數, 深頁OFFSET及包含式搜尋會隨目前工作區邀請數成長, 不能稱固定成本
7. 隔離測試加入其他工作區500筆及本工作區25筆, 驗清單兩頁、無重複、正確欄位、特殊字搜尋及接受／到期排除；MySQL EXPLAIN確認workspace_invites_pending索引。Mail fake驗新地址、加密、重試、寄前接受／撤權, 瀏覽器驗接受後移往成員與原帳號保留
8. 沒有以本地測試宣稱部署效能驗收或遠端p95。其他已知測試缺口仍回填TT-336／TT-334, 不宣稱整個專案驗收通過

## Migration與環境

2026_11_14_120000只新增mail_deliveries.invitation_id、一對一索引與workspace_invitations兩個索引, 不seed、清空或重填既有資料。先在隔離SQLite／MySQL執行, 再用主服務pretend檢視SQL後套用精確路徑；相關紀錄在忽略的artifacts/tt340

自動驗證只使用合成帳號與Mail fake／無寄信Worker的隔離佇列, 不額外向Josh實寄測試信。是否正式寄到信箱仍由後續實際邀請與驗收確認, 不能以fake測試冒稱已收件

## 限制

既有已建立的邀請沒有補寄, 清單顯示「尚無寄送紀錄」；需要寄信時重新建立同地址邀請。新流程不增加封鎖名單、撤銷／重寄API或自訂邀請角色, 這些不在本次紙條要求

驗證與提交結果記在plans/TT-340/step.md及卡片, 沒有推送或雲端部署
