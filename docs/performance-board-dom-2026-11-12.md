# TT-181 500卡看板DOM與虛擬化評估

2026-11-12。**完成量測與策略評估；兩個試驗均已撤回，沒有將虛擬列表或CSS containment放進產品。** 首次掛載長任務仍待TT-256處理，不將本卡結案說成大型看板已完全優化。

## 資料、環境與方法

- 5個Epic＋495個Task，交錯3個狀態、不同長度標題／負責人／估時；真實Project、卡片API及SQL資料，沒有業務mock。
- 僅使用隔離tasktracker-perf-test／tasktracker_test，HTTP13029；工具硬性拒絕其他API位址。fixture帳號與參照只保存在忽略的frontend/artifacts/board-performance/fixture.json（0600），不寫入業務庫或Git。
- production Vite build，Chrome157.0.8103.52，1440×1000、另驗768與390。CDP 4x CPU作可重跑的受限CPU樣本；資產快取為暖、API仍真實執行，不能當作部署API延遲或真實裝置SLO。
- Default看板按原100筆HTTP頁讀取5頁，完整500卡後才量；約11,975個DOM節點（控制／試驗皆含量測注入的style節點）。未下載描述、留言／歷史，也沒有getWorkspace／getTodos。
- 每組交錯3次control／candidate。先量20次跨頁垂直捲動＋6次viewport寬度切換，再量20次不同欄位dragover回饋；實際DnD另驗證。CDP的layout／style與PerformanceObserver長任務分開，不把腳本等待rAF的總耗時當JS CPU。
- 最後一輪以500卡settle後的readyAtMs切分initialLongTasks及scenarioLongTasks。早期試驗只記全程longTasks，不將那些任務全部推定為首次掛載。

完整去識別原始統計：[board-dom benchmark](../.ecc/benchmarks/board-dom-2026-11-12.json)。初始、純CSS、v-memo及完整元件CSS的試驗都有保留；原始畫面／fixture留在忽略的artifacts。

## 單一假設試驗與決策

### A. CSS content-visibility原型：局部有收益，整合後不穩定，撤回

保留全部卡片，只以`content-visibility:auto`／`contain-intrinsic-size`延後離屏內容；另試詳細／精簡高度，以及focus／hover／moving正常呈現。這不是DOM虛擬化，卡片數與資料完全相同。

局部原型與最後重跑中，layout常下降，但style及內容活化有額外成本。例如最後未改產品的對照試驗中位數：

| 500卡scroll＋resize | 原樣控制 | 局部CSS候選 |
| --- | ---: | ---: |
| layout duration | 112.41 ms | 67.95 ms |
| recalc style duration | 16.04 ms | 32.45 ms |
| rAF／resize整段elapsed（非CPU） | 747 ms | 533 ms |

然而把候選套入完整元件、加上focus／hover／moving時，另一輪3次樣本的layout中位數從91.54→119.82ms，style從12.94→74.88ms。局部原型結果不能直接當上線改善，CSS計算與活化抵銷收益；因此**撤回全部產品CSS與large-board class**，不以其中最好的一筆宣稱加速。

### B. Vue v-memo原型：無穩定互動收益，撤回

試過卡片VNode重用，依卡片物件／密度／moving／資格／日期／狀態／關聯索引決定失效。20次dragover回饋的control elapsed中位數約453→543ms，JS counter亦未呈現穩定下降。這包含frame等待與測量波動，不宣稱v-memo必然變慢；在無穩定收益下，不保留額外失效依賴與維護風險。

### C. 虛擬化取捨

目前3欄、多Epic泳道、可變卡片高度與原生DnD／鍵盤焦點／底卡，不能只因DOM超過某門檻就引入虛擬列表。窗口化需要處理移出視口的拖曳目的地、焦點與瀏覽器搜尋、泳道高度、捲動錨定與資料撤權，成本尚未有對照證據支持。

因此本輪不新增套件、不截斷卡片、不新增內層垂直捲軸。保留Default全部500張卡，真實試驗結果作為下一步依據。

## 未解瓶頸與後續卡

最後control樣本的500卡settle前long task分別為89／157／216ms、131／174ms、133／236ms；readyAtMs約967–1392ms，包含5頁真實API與瀏覽器工作，不能直接當純render時間。4x CPU下仍有可感知主執行緒阻塞，不能宣稱沒有問題。

已查重並建立 **TT-256「分批掛載大型看板以降低初次渲染長任務」**，open、指派taskTrackerAI，放效能改進＋實際功能看板。下一步先用trace區分API／DTO處理／Vue掛載，再評估跨frame分批掛載；最終仍呈現完整卡數，scope切換／撤權須中止舊批次。這是尚未實作的後續，不把本卡策略評估當成已完成掛載優化。

未量到GC、實際記憶體容量與跨瀏覽器RUM，不能用本機Chrome推出Safari／Firefox的容量或INP／p95承諾。

## 最長欄底卡驗證揭露的Epic初始化修正

收尾把「DOM最後一張」改成實際最長欄底卡後，開啟的是帶彙總估時的Epic。其詳細DTO估時是顯示用加總，編輯器type watcher會正規化為0，但原dirty基準在正規化前保存，導致未修改也出現捨棄草稿確認，瀏覽器測試預設取消後留下抽屜。

已在IssuePanel.resetForm先正規化Epic不可寫的估時／歸屬，再記基準；不是把關閉確認一律接受或放寬dirty保護。新增斷言：開啟後未修改，關閉抽屜需卸載且完全沒有確認視窗。500卡最長欄底卡、泳道收合400→展開500、F2／DnD／搜尋與三尺寸重跑通過，既有production看板與composables亦通過。此為驗證時發現的初始化正確性修復，與被撤回的渲染原型分開記錄。

第一筆評估提交後補強檢查曾失敗，故本卡當時維持inprogress；修正驗證後另筆同卡提交，不重寫歷史。開發期間新增AGENTS的「commit不帶AI署名」規則已讀取並沿用。

## 正確性與資料存取驗證

- 正常production及CSS候選都實際驗F2、底卡與DnD；原樣production最後再次測500卡、Epic泳道／收合來源、精簡／詳細切換、搜尋Work495只剩1卡再清除回500、三尺寸與無橫向溢位／pageerror。未改業務回應、權限或狀態語意。
- 依實際bottom座標選最長欄的底卡（不是DOM最後一欄的最後一張），可以scrollIntoView、完整進入viewport、取得焦點並開抽屜；拖曳跨狀態後仍500卡。沒有用display:none或只列第一頁來降低DOM。
- 既有production `test:board`三尺寸回歸與composables通過；最終重建production build成功。產品ProjectBoard.vue與TT-240提交版本完全一致，兩試驗無殘留。
- 沒有新增API／輪詢；資料範圍仍當前Project／Board，100筆頁與完整去重／總數檢查。此卡未解TT-180、輪詢、Session鎖等其他問題。

重跑（第一次會在隔離服務建立500卡）：

```sh
E2E_API_URL=http://127.0.0.1:13029 \
PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' \
node frontend/tools/board-performance-check.js
```

加`E2E_REUSE_FIXTURE=1`可重用忽略的同服務fixture；`E2E_CPU_RATE=1`取消4x節流；`E2E_INTERACTIONS_ONLY=1`只驗操作、不覆寫measurements.json；`E2E_CANDIDATE_INTERACTIONS=1`可驗試驗CSS下的操作，不是啟用產品功能。不要把測例指到業務庫。

## TT-256 分批掛載（2026-11-12）

**單一假設**：初次長任務主要來自5頁卡片收齊後，Vue一次掛載500張卡。改為`ProjectBoard`跨frame分批掛載：首批50張，之後每frame加50張直到全部；欄位標題計數與「顯示中 x / n」進度一律用完整數量，`aria-busy`在完成前為true。只記數量、不保存卡片，Project／看板切換重設批次，撤權或新載入直接依目前props重算，舊scope的卡片不會插回。搜尋清除等一次增加超過一批時同樣分批。

**量測**（`frontend/tools/board-mount-check.js`）：production build、CDP 4x CPU、真實隔離MySQL API（每頁100筆）、500卡，A（原版）／候選build交錯各3輪，取中位數；時間以最後一個卡片頁`responseEnd`為起點。

| 指標（中位數） | A 原版 | 每批100 | 每批50（採用） |
| --- | ---: | ---: | ---: |
| API完成後最長long task | 161～179 ms | 96 ms | **62 ms** |
| API完成後long task合計 | 274～286 ms | 298 ms | **122 ms** |
| API完成→首張卡出現 | 約120 ms | 46 ms | 約40 ms |
| API完成→全部卡片出現 | 291～302 ms | 361 ms | 470 ms |

兩次A樣本各自與同輪候選比較（161 vs 96、179 vs 62）。代價是全部卡片晚約170 ms才全數出現，期間有進度提示，主執行緒每frame可回應輸入。這是本機Chrome實驗室樣本，不是RUM／INP或跨瀏覽器保證；API完成前的DTO解析與5頁請求等待沒有改動。

**回歸**：`board-performance-check`（500卡底卡／F2／拖曳／泳道收合展開／密度／三尺寸，`E2E_INTERACTIONS_ONLY=1`）、`board-ux-check`、`board-query-check`、`poll-drafts-check`、`browser-check`通過；`board-mount-check`另斷言分批中欄位標題合計等於完整卡數。未引入虛擬化依賴，DOM最後仍是全部卡片。
