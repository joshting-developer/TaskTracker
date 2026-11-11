# 任務列表、詳細與單卡工時資料分責

日期：2026-11-12。承接TT-237～239看板接線，處理TT-214／215／244，TT-240先完成卡片編輯與篩選選項；其他用途仍續作。

## 端到端範圍

ProjectTasksWorkspace取代只管理看板的BoardWorkspace，讓列表／我的任務、看板及其詳細／編輯共用明確的頁面owner。useProjectTaskQueries負責查詢與可見性／取消，不處理表單；useIssueActivity負責留言、歷史、工時、子卡各自的分頁生命週期。App的這些route先auth/current＋指定Project，不再呼叫getWorkspace/getTodos；首頁、ProjectHome與其他管理／月曆仍有獨立P1，不因本輪完成而結案。

列表的search／多狀態／優先級／指派／類型／Epic／情況／排序／scope／page送到SQL，不對下載的全集再切片。每頁20、最大100，帶total／lastPage；篩選重設頁碼，資料減少超出最後頁時回到仍有效頁，不放寬條件。純列表IssueListResource只輸出識別、標題、類型、狀態、優先級、指派姓名、期限、revision／日期；不查工時SUM、Epic估時或活動，只預載需要的Project／Status／Assignee。

深連結按ID讀完整可編輯欄位與最新revision，關聯ID及最小參照保留；活動獨立20筆分頁，不以預設空陣列覆蓋。關聯顯示使用已預載狀態，Resource不lazy load；明確關聯限200，SQL最多201以辨識歷史超限，超限回CONFLICT而非默默截斷後允許覆蓋。Epic／父任務子卡由children端點每頁20／最大100讀取，完整頁按「關聯」tab才讀，編輯器只在確實顯示子任務區時讀。

## 單卡工時

詳細的SUM在SQL依timeEntry.readOwn／readTeam交集限定作者，只有具資格才帶spentMinutes；明細依工作日／建立時間／ID穩定倒序，排除void，Epic本身與所屬卡每筆只算一次。工時明細附當筆卡片的最小識別，從Epic修改子卡紀錄時仍顯示並鎖住原卡，不改掛Epic。

填報成功後只刷新目前詳細／活動；草稿與送出中的revision不被背景資料更換。工時409先重取明細，既有抽屜的editing快照維持；使用者關閉後再開會取得新revision，不會陷入永遠重用舊版本的衝突。每日24h、作者限制、同requestId重試及撤權清除原則不變。

## 選項（TT-240部分）

新增issue-options的最小DTO／Request／Service／Repository／Resource；kind限定issue／epic／parent，Project與排除自己、父卡資格、文字搜尋及穩定分頁在SQL，最大100、UI30。候選沒有描述／工時／歷史／revision，不能當完整編輯基準。member-options只回同Project有效成員id／name。

usePagedLookup＋PagedSelect按需讀取／搜尋debounce／取消與generation，保留當前已選參照；表單與篩選的指派／Epic、父任務／關聯使用遠端候選，不靠第一頁100張卡假裝全集。選到不在原頁的候選也先保留最小參照，儲存時原有關聯不消失；寫入資格仍由原服務交易內重驗。Project負責人／轉卡Project／月曆任務等其餘用途尚未接線，TT-240保持inprogress。

## 八項存取審查與證據

1. 每頁只取表格欄位；單卡描述／關聯、各類活動、候選欄位各自分開。
2. 每個列表／活動／候選均有SQL上限與穩定tie-breaker；明確關聯最大200且超限明確拒絕。
3. 列表篩選／我的任務／排序／分頁與候選搜尋在SQL；前端不把當頁資料再切片冒充總集。
4. 列表／看板／其內詳細與編輯無Workspace／Todo預設全集。其他頁面仍未完成，見原卡。
5. 描述在開詳細時；活動按tab；選項按用途；子任務按實際區塊；背景只對目前scope。
6. 單頁查詢不隨活動數成N+1；純列表不碰time_entries／issue_comments／audit_events。定時備援與正式runtime／版本同步仍另卡，不以改回本地掩蓋未解問題。
7. 瀏覽器104張卡驗證列表20筆、我的任務1筆、第二頁、SQL描述搜尋；103張看板跨兩頁；搜尋不在候選第一頁的卡加入關聯，原連結與描述保存。活動／工時／children分責，Network bulkReads=0。HTTP測試107張看板與106子卡的分頁、欄位及無權Project隔離。
8. TT-240其餘用途／TT-180首頁與導覽、Todo／設定／月曆／通知、同步版本／DOM／bundle／runtime與量測均仍待原卡續作，不宣稱全部P1通過。

## 驗證

- 純協定API client及composables檢查通過。
- 原生MySQL驗有效工時27筆分成20＋7、作廢排除、Epic direct＋child去重SUM、本人範圍排除另一作者、team可見；Reader無資格403／撤權404。
- 真實Chrome完整工時流程通過：三尺寸、列表類型篩選返回保留、當日預填、整數分鐘、409草稿、關閉再開新revision、網路遺失回應同requestId、每日上限、Epic／子卡不改掛、管理者不可改別人、降權清表單。
- 完整預設memory：72 passed／3185 assertions、8 native skips；獨立MySQL：80 passed／3306 assertions。Pint14檔通過。
- 沒有對現用本地／外部業務庫跑seed／RefreshDatabase或壓測；沒有新增PAT／MCP／工具專屬整合。瀏覽器與負載證據不宣稱為正式RUM或p95。

- 最終看板UX回歸仍通過單一垂直捲動／底卡、Epic收錄／狀態、偏好與拒絕storage回退、密度、雙擊／鍵盤／手機編輯三尺寸；Vite build通過但大chunk仍由TT-183追蹤。
