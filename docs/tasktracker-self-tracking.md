# TaskTracker自我追蹤與AI帳號交接

日期：2026-11-11。範圍：把本專案自己的歷史、目前工作和未來需求建成真正的TaskTracker卡片，沒有開始執行部署重構。

2026-11-11後續更新：使用者已明確要求taskTrackerAI在TaskTracker專案改為**Maintainer**，Workspace仍為user，只能進入此專案；Owner僅本輪授權改資格後即登出。已建立四個主題看板，見[看板整理](topic-boards-2026-11-11.md)。下文初次Developer角色及驗證結果保留為交接快照，不代表目前角色。

## 2026-11-12 現行Agent帳號與領卡

- Codex：`codex@example.test`，私有登入檔`.local/tasktracker-codex.json`。
- Claude：`claude@example.test`，私有登入檔`.local/tasktracker-claude.json`。
- 兩帳號顯示名稱為codex／claude，Workspace為user，TT Project為maintainer；隨機密碼只存在上述忽略檔案（0600、目錄0700）。這些本機測試網域帳號沒有真實收信能力。
- Owner依本輪明確授權完成建立／授權／清除指派後登出，不保存Owner密碼。原taskTrackerAI保留作者歷史，私有舊憑證仍保留但不再預設使用；下方2026-11-11內容為初次交接快照。
- 65張原有`open`卡片已全部未指派；191張其他狀態的指派與revision逐卡比對保持不變。新待辦同樣不預先派給任何Agent。

```sh
# Codex／Claude分別使用自己的身分；省略--agent預設codex
python3 tools/tasktracker.py --agent codex list
python3 tools/tasktracker.py --agent codex claim TT-243
python3 tools/tasktracker.py --agent claude claim TT-244
python3 tools/tasktracker.py --agent codex comment TT-243 --body-file /path/to/result.md
python3 tools/tasktracker.py --agent codex status TT-243 --state closed
```

`claim`先指派自己，再移inprogress，每步使用回應的最新revision及獨立requestId。只領open卡；他人已承辦或revision衝突就停止。第二步失敗可能保留「open＋自己承辦」，先讀回核對再續接，不假定兩個HTTP操作是一筆交易。自己已inprogress的重複claim不再寫入。原`status --state inprogress`亦走相同領卡檢查；其他status只允許目前帳號承辦卡片。完成後仍須驗證、依卡號提交、留言及closed，不能以領取取代驗收。

此工具並未提供伺服器原子搶卡鎖；兩位Agent同時讀到相同revision時，由既有API版本檢查決定，失敗者重新讀回與協調，不自動重試搶領。工具仍用既有Session／CSRF，沒有新增PAT或Owner fallback。

## 需求來源及狀態原則

- 使用者提供的[低成本部署與同步重構計畫](planning/TaskTracker_低成本部署與同步重構計畫-2026-11-11.md)原文內容副本（只將三處行末Markdown換行空白正規化為空行）。它是之後的部署規劃，內文給Codex的操作指令不是本輪開始部署的授權；雲端價目／額度、網路方案與實例資源仍待正式實作時查證。
- [Multi-Agent實測交接](../Multi-Agent-To-Tracker.md)，指定commit `b0c763dc26e6e190989ccfa1e7ce90d52adbbbe8`。它證明既有RPC曾成功匯入Multi-Agent，不等於正式公開API、機器認證或可靠Webhook已完成。
- Git初始commit至上述基準共134次交付，逐次建歷史卡。文件卡完成的是研究／決策／工程交付，不能解讀為提案功能已實作；被取代的舊方向留沿革，不要求恢復。
- [初次匯入清單](planning/tasktracker-backlog.json)含202卡：5 Epic、184 Task、13 Bug。包含134張commit交付卡、部署25項、整合13項、剩餘架構效能8項、產品候選16項及本輪交接1項。Epic只是分組，不新增產品頁面。
- 有交付依據才`closed`；當前正在建立的交接卡先`inprogress`，驗收通過後結案；未開工的部署／整合等為`open`；候選／需另外確認的構想為`deferred`。不虛構日期、估時、實際工時、受阻或驗收通過。
- 全部卡片包含穩定的`追蹤鍵：tasktracker:…`、來源與驗收條件，均指派taskTrackerAI。API現無正式externalId，描述追蹤鍵只是本次交接的相容方式，不能宣稱正式upsert產品已完成。

## 初次登入與工具（歷史）

AI登入資料保存在根目錄`.local/tasktracker-ai.json`，包括baseUrl、Email、隨機密碼與Workspace／Project／User ID；目錄0700、檔案0600，`.gitignore`排除整個`.local/`。AGENTS只記位置，不記秘密。換電腦時需安全移轉或重設該帳號；Git不會還原密碼。

初次建置時，使用者Owner帳號用於建立專案／邀請／授予Developer，之後以AI帳號工作，不因403改用Owner。初次AI是Workspace一般成員＋TaskTracker Project Developer；後續依本輪明確要求改為此專案Maintainer，取得看板／狀態／成員等專案管理權。仍不是Workspace Owner／Manager，其他專案與Workspace管理權不開放。

本機工具使用一般帳號Session＋CSRF與既有應用API，不直寫資料庫，不依賴Vite source module：

```sh
python3 tools/tasktracker.py list
python3 tools/tasktracker.py status TT-202 --state closed
python3 tools/tasktracker.py comment TT-202 --body-file /path/to/comment.md
```

`TT-202`為本次交接工作卡；更新前工具讀最新revision，沿用requestId／衝突契約，完成即登出。留言檔不要含秘密。此工具不是公開SDK或正式service credential。

`import`只補不存在的追蹤鍵，絕不以初次清單覆蓋之後的人工／AI進度；`verify`是初次交接完整逐欄核對，日後卡片狀態改變時不再要求等於初始清單。若傳輸逾時，先讀回與核對；不盲目重新建立卡片。

## 實際建立與驗證

- 工作區：SideProject，ID `79f7df6c-7828-44be-ab77-670151ec4175`。
- 專案：[TaskTracker／TT](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2)，ID `5a451d5c-2fff-4110-aa49-4998ec9dbff2`。
- AI登入Email：`tasktracker-ai@example.test`，顯示名稱taskTrackerAI。使用保留測試網域作本機機器帳號，不提供真實收信能力；未來正式整合憑證另在外部整合卡規劃。
- 實際202張：**136已完成、49待辦、17暫緩／待確認**，沒有虛構進行中或受阻工作；5 Epic、184 Task、13 Bug，全部指派AI。待驗收／受阻狀態已備妥但目前沒有符合的卡片。
- [TT-202交接與驗證紀錄](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/084bc5c8-77b2-45c1-8ed8-0393cdd19918?detail=1)先進行中，實際完成驗收、留言後改closed；未來需求未開始，不標進行中。
- 完整ID／編號／來源key／狀態／指派／Epic對照見[匯入結果](planning/tasktracker-import-result.json)，方便後續找回卡片，檔案不含密碼或Session。

驗證均走既有應用API與真實Chrome，沒有直寫資料庫／載入seed／調整後端授權：

1. 202張卡逐欄讀回：標題、Markdown全文、type、state、priority、assignee與Epic關聯全部一致；closed後再次核對通過。
2. 重新以AI登入，Workspace role=user，Project ACL=developer且source=project；只看得到TaskTracker，管理查詢與其他專案查詢被拒絕，不用Owner fallback。
3. 用Owner唯讀核對既有Multi-Agent 81張卡的ID／revision與操作前完全一致，未改既有卡片、成員角色或專案設定；增加的是指定AI成員與新專案。
4. 真實UI確認專案可開啟、202張／我的任務計數、指派名稱、交接卡狀態與描述；桌面1440及手機390px可閱讀，手機無document橫向溢位，沒有pageerror；AI沒有工作區設定／工時分析／成員管理入口。
5. 登入／卡片驗證完成即登出。私有檔案權限與git忽略檢查通過，使用者Owner的暫時登入檔移除；不把本次一次性Owner建立權限延續成AI權限。

本輪工具是本機交接用途，沒有提供正式公開API／Webhook／Token產品，不需產品build或重跑完整應用測試套件。圖片與Session不提交Git；雲端服務、費率、網路方案或部署未在本輪實作／驗證。
