# TaskTracker主題看板

日期：2026-11-11。使用者要求依「SEO、部署、實際功能、AI整合/API」設看板，並後續明確要求把taskTrackerAI在此專案改為Maintainer。這是既有產品資料整理，沒有新增看板功能或Issue類型。

## 已建立

[TaskTracker看板列表](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/boards)

| 看板 | 實際收錄張數 | 明確選取張數 |
| --- | --- | --- |
| [SEO](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/board/b1919fd9-4371-4ed9-8416-0037288fc0e2) | 5 | 5 |
| [部署](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/board/8de358c8-f508-4ea4-a833-7ed474718238) | 49 | 49 |
| [實際功能](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/board/2465f096-8217-4c78-93ab-02b63beb0fb4) | 110 | 110 |
| [AI整合/API](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/board/80f5c059-aba8-4caa-8ac5-8014c5c58a14) | 42 | 28 |

Default保留全部210張（原209張＋本輪整理卡TT-210）。原卡片狀態、負責人、描述、父子／Epic／關聯均保留；已完成歷史也依內容分類，不因換看板重新開工。

## 分類規則

- **SEO**：公開入口、訪客TopBar、公開介紹頁、SEO／分享及搜尋收錄，TT-205～209。
- **部署**：正式runtime／單Fly映像、資料庫／遷移／備份、容器／Redis／Queue／Reverb、智慧輪詢與跨雲／資源部署工作；保留其歷史交付。公開入口整合驗收TT-155在此，頁面內容工作則在SEO。
- **實際功能**：任務／看板、Workspace／ACL、待辦／通知／工時、一般UI／流程及產品候選；前端與一般功能品質改善也在此。
- **AI整合/API**：公開REST／PAT／外部同步／Webhook、API與應用層契約，以及歷史AI skills／工程規範／交接。這些歷史工程卡不代表新增工具專屬產品或恢復內建Agent工作流。

原有4張跨主題總覽Epic（TT-1歷史、TT-2部署與同步、TT-4架構品質、TT-5產品候選）只留Default。若直接收錄它們，現有Epic展開規則會把其他主題卡片一起拉入，所以本輪以實際工作卡明確收錄。各主題不重複，206張主題卡＋4張跨主題總覽=210。

AI看板收錄TT-3這個專屬API Epic，依既有產品規則動態展開其14張所屬卡，另外明確收錄歷史API／AI工程卡；28個選取項目實際顯示42張。分類與所有ID／標題見[本次清單](planning/topic-boards-2026-11-11.json)。

這些是主題看板，不是狀態篩選或新的自訂Issue type。除了TT-3的動態Epic收錄，其餘收錄是目前快照，沒有自動文字分類器。未來建卡時應帶入對應boardId或更新收錄；不要重跑本次清單覆蓋之後人工調整。

## 權限變更

初次AI是Project Developer，沒有board.manage。使用者先授權本輪Owner操作，後續又明確要求改為Maintainer；因此Owner只授予taskTrackerAI這個Project的maintainer，隨即登出。四看板再由AI自己透過應用API建立，不直寫資料庫。

目前有效資格是Workspace user＋TaskTracker Project maintainer；具board.manage／project.statuses.update／project.members.manage等專案管理權，仍不是Workspace Owner／Manager，也不能存取Multi-Agent。實際權限由後端permission＋scope判斷，不依本機設定檔的角色文字放行。Owner密碼未寫入檔案、卡片或Git，既有AI私有登入檔仍以0600／目錄0700保存。

初次Developer的[交接與驗證快照](tasktracker-self-tracking.md)保留沿革，本次最新資格在該文件及AGENTS說明，未重寫最初匯入manifest。

## 驗證

- Owner讀回ACL確認source=project／roleId=maintainer；AI重新登入確認Workspace role=user，只有TaskTracker可見且含board.manage。
- 四個看板及Default讀回核對，沒有同名重複；逐板selectedIssueIds與人工分類清單一致。直接使用前端includedIssues函式核對Epic展開，四板各為5／49／110／42，Default為210。
- 建板前後卡片完整資料一致；更早的原209張卡也逐筆比對完整內容及revision未變。本輪TT-210是新增的整理工作紀錄，驗收後已留言交接並設為closed。
- 真實Chrome登入AI，確認五個看板入口、逐板開啟名稱與渲染卡片張數；手機列表無橫向溢位／pageerror。截圖在忽略的frontend/artifacts/topic-boards，不進Git。
- 未改產品程式、DB schema、狀態設定或他專案資格；資料整理不需重跑應用build／全套測試。

## Bug看板（2026-11-12）

使用者要求新增「Bug」看板收錄缺陷卡：[Bug](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/board/13240acd-c473-47ce-8ea0-e9f0f07aa4f7)（ID `13240acd-c473-47ce-8ea0-e9f0f07aa4f7`），由claude帳號經應用API建立。首張收錄TT-258（狀態篩選勾選導致Chrome頁面崩潰）。

- Bug看板是橫切看板，與上方主題看板交叉收錄，不取代主題分類；TT-258同時收錄於「實際功能」。
- 之後回報的缺陷先查重，建卡時帶`boardId`或更新收錄到Bug看板，並依主題收錄對應主題看板。不整包收錄跨主題Epic。
