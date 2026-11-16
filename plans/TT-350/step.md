# 執行紀錄

- 第2版已私人發布成功, environment revision20；33項合成測試通過。who_am_i回Josh／SideProject／write, list_projects取得TT及MA, get_issue以TT-350精準取得revision13。沒有再出現NOT_CONFIGURED、TOKEN_REJECTED或READ_SCOPE_REQUIRED
- build與官方package helper清理產物目錄遇EPERM, 手動覆寫兩個相同build輸出, helper產出的archive清單及原生save回傳均為2檔。來源子樹085f71c6b8bc6c15818e9c15cc058f4141f5bfd2, deployment appgdep_6afa67dfcb048191b29de717e2a8d158 succeeded。原有其他任務差異未提交
- 本輪未做正式PAT撤銷／過期、log檢查或紙條／建卡寫入；紙條不在現有8個工具內。公開PAT無正式工時寫入能力, 本輪實際工時尚未填報, 不以留言替代

- 2026-11-16：Sites工具恢復, get_site確認唯一owner為joshting.1999@gmail.com。舊部署只套用env revision1, 重發revision18後NOT_CONFIGURED改為TOKEN_REJECTED
- 使用者新PAT已透過原生Secret工具更新並私人發布revision20, 現為READ_SCOPE_REQUIRED。根因是Bridge只認read, 未按正式PAT契約接受write包含read。修正單一共用判斷, 增加write唯讀成功與未知寫入工具仍拒絕的合成回歸
- 本次為使用者指定GPT串接優先的受阻卡修復, TT-398已有其他對話進行中差異, 不搶改其狀態或提交內容；TT-350驗收完成前仍保留blocked

- TT-349已提交並push, 正式時間30分鐘, 卡片closed；讀回最新紙條後開始TT-350, 僅此卡inprogress
- 原生Sites來源helper會另初始化巢狀Git, 與使用者原repository規則衝突；本卡改從原repository匯出Adapter子樹, 仍使用官方package helper及原生私人發布, 不複製整個TaskTracker或改寫main歷史
- 原repository以git subtree split匯出Adapter, Site來源SHA 94a252ef3446e44a4c5e5adb9cecaf8f19ae704e, push後讀回分支一致, 未新建clone／worktree、未改main歷史、未傳完整TaskTracker
- 官方package helper打包本地受測Worker, 原生owner-private發布成功, Site版本1, deployment appgdep_6af978ef11748191bfffd71a2595e248, env revision3
- 私人網址https://tasktracker-private-bridge.consideratecheetah3.chatgpt.site, has_mcp=true, 讀回policy仍僅owner一人／無群組／無外部訪客, 匿名health與mcp實際401
- 原生回傳Plugin ID plugin_asdk_app_sites_0844809a2eac8191b56b64b3bab240ad, 安裝工具回Already installed；目前對話工具表尚無8個新tool, 不能宣稱已呼叫
- 只配置owner email（Secret）及SideProject workspace ID, 沒有TASKTRACKER_PAT。真實唯讀E2E、執行時Secret／出站、撤銷驗證仍受阻, 不重用Agent PAT或假裝已連線。需Josh安全設定專用短效唯讀Token及新聊天呼叫, 發紙條後繼續獨立任務

## 正確帳號重新發布

Josh於2026-11-15確認現在登入的joshting.1999為目標帳號。舊Site在目前帳號not found且owner清單空, 不刪舊服務。此份來源依明確重建要求解除舊project_id, 下一次create回傳立即原樣保存新ID, 不重複建立。舊ID仍留docs/chatgpt-bridge-deployment.md沿革

新帳號owner-only已讀回, 私人版本1發布成功, Plugin已安裝但當前對話尚無工具。32項Adapter測試通過, 匿名HTTP401。沒有TASKTRACKER_PAT, 真實唯讀E2E及撤銷驗收仍受阻；發紙條給Josh後回TT-364
