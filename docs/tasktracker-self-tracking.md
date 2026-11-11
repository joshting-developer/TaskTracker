# TaskTracker自我追蹤與AI帳號交接

日期：2026-11-11。範圍：把本專案自己的歷史、目前工作和未來需求建成真正的TaskTracker卡片，沒有開始執行部署重構。

## 需求來源及狀態原則

- 使用者提供的[低成本部署與同步重構計畫](planning/TaskTracker_低成本部署與同步重構計畫-2026-11-11.md)原文副本。它是之後的部署規劃，內文給Codex的操作指令不是本輪開始部署的授權；雲端價目／額度、網路方案與實例資源仍待正式實作時查證。
- [Multi-Agent實測交接](../Multi-Agent-To-Tracker.md)，指定commit `b0c763dc26e6e190989ccfa1e7ce90d52adbbbe8`。它證明既有RPC曾成功匯入Multi-Agent，不等於正式公開API、機器認證或可靠Webhook已完成。
- Git初始commit至上述基準共134次交付，逐次建歷史卡。文件卡完成的是研究／決策／工程交付，不能解讀為提案功能已實作；被取代的舊方向留沿革，不要求恢復。
- [初次匯入清單](planning/tasktracker-backlog.json)含202卡：5 Epic、184 Task、13 Bug。包含134張commit交付卡、部署25項、整合13項、剩餘架構效能8項、產品候選16項及本輪交接1項。Epic只是分組，不新增產品頁面。
- 有交付依據才`closed`；當前正在建立的交接卡先`inprogress`，驗收通過後結案；未開工的部署／整合等為`open`；候選／需另外確認的構想為`deferred`。不虛構日期、估時、實際工時、受阻或驗收通過。
- 全部卡片包含穩定的`追蹤鍵：tasktracker:…`、來源與驗收條件，均指派taskTrackerAI。API現無正式externalId，描述追蹤鍵只是本次交接的相容方式，不能宣稱正式upsert產品已完成。

## 登入與工具

AI登入資料保存在根目錄`.local/tasktracker-ai.json`，包括baseUrl、Email、隨機密碼與Workspace／Project／User ID；目錄0700、檔案0600，`.gitignore`排除整個`.local/`。AGENTS只記位置，不記秘密。換電腦時需安全移轉或重設該帳號；Git不會還原密碼。

使用者Owner帳號只在本次建立專案／邀請／授予Developer時使用，之後以AI帳號工作，不因403改用Owner。AI是Workspace一般成員，只授權TaskTracker Project Developer，沒有Owner／Manager／專案成員管理權。

本機工具使用一般帳號Session＋CSRF與既有應用API，不直寫資料庫，不依賴Vite source module：

```sh
python3 tools/tasktracker.py list
python3 tools/tasktracker.py status TT-202 --state closed
python3 tools/tasktracker.py comment TT-202 --body-file /path/to/comment.md
```

`TT-202`為本次交接工作卡；更新前工具讀最新revision，沿用requestId／衝突契約，完成即登出。留言檔不要含秘密。此工具不是公開SDK或正式service credential。

`import`只補不存在的追蹤鍵，絕不以初次清單覆蓋之後的人工／AI進度；`verify`是初次交接完整逐欄核對，日後卡片狀態改變時不再要求等於初始清單。若傳輸逾時，先讀回與核對；不盲目重新建立卡片。

## 實際建立與驗證

待實際建立後記錄Project／卡片網址、角色、狀態分布、API逐欄及UI驗證與原專案保留結果。
