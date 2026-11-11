# API 資料存取架構複審

日期：2026-11-12。範圍為 `887e458`、`fd05255` 及本輪修正。依專案 Addy performance-optimization 主診斷、ECC benchmark 補驗證；本輪不是全站效能驗收。

**結論：部分符合，仍有 P1 未完成，整體效能驗收未通過。** 新增有界 API 不等於原本全量載入與輪詢已移除。

## 八項檢查

| 規則 | 審查結果／證據 |
| --- | --- |
| 1. 呼叫端欄位與範圍 | Project列表：識別、名稱、介紹、負責人摘要及本人能力；單Project另取狀態。Issue列表：編號、標題、類型、狀態、優先級、指派、到期日、估時與revision等摘要；詳細另加描述。留言／異動另有端點。均限定目前Session工作區及有效Project權限，見[契約](query-api-v1.md)。前端尚未切換，不能稱已滿足所有頁面。 |
| 2. 上限與穩定排序 | 新Project／Issue／留言／歷史列表預設20、最多100，SQL LIMIT／OFFSET；Project updatedAt＋ID、Issue updatedAt＋identifier＋ID及活動position＋ID提供tie-breaker。舊getWorkspace／getTodos仍無界。 |
| 3. SQL篩選與分頁 | 新Issue篩選／搜尋／排序在SQL。重審發現原Project分頁前仍取全空間參照及指派；本輪改成SQL依有效permission映射篩可見Project，授權快照只讀當頁ID。搜尋含LOWER／前置萬用字元、COUNT及深頁OFFSET仍可能掃描更多列，尚未取得遠端EXPLAIN／成長基準，不宣稱查詢時間固定。 |
| 4. 不預設載整個Workspace | 新API符合指定Project或分頁範圍；Project參照單頁最多100，單Project最多1。App.vue仍以getWorkspace作預設來源，屬P1未完成。 |
| 5. 詳細與活動按需 | 新Issue列表不選description、留言、歷史；詳細不附活動全集。重審測試另抓到AccountService.context經RecordStore附帶歷史，本輪改為只讀Workspace識別／名稱及目前成員role／有效資格。前端編輯、關聯與工時仍須拆接，Todo內容／活動也未完成。 |
| 6. 頻率、RTT、查詢次數、bytes成長 | 健康探針零SQL；看板selected IDs批次查Project；單日工時SQL SUM；留言／歷史子表差量寫入。新授權參照與投影回傳量受頁大小限制，但每次仍含多個授權／關聯SQL，遠端RTT不可忽略。全量30秒刷新／事件刷新仍存在，尚未完成背景請求與真實拓樸p95評估。 |
| 7. 測試驗證讀取範圍 | 新範圍成長測試由1增加至36個同工作區Project，指定Project回應不變，參照SQL綁定只有指定ID且LIMIT1；5筆列表的授權參照只綁定當頁5個ID且LIMIT5。另驗證無audit／留言讀取、列表20／3分頁、精準ID與literal搜尋、私有Project／Reader／撤權／跨Project及Workspace標頭。不限於計算查詢次數。 |
| 8. 未處理P1明列 | 下表所有未完成項目保留追蹤，不能以本輪測試通過宣稱整體效能驗收。 |

頁數與單頁列數有界，不代表所有SQL掃描列數、單欄字串bytes、完整流程請求數或延遲已具常數上界。後續量測須分開記錄這些指標。

## 本輪直接修正

- `ProjectReadRepository`／`ProjectReadService`：撤掉全空間Project參照預載，SQL先篩有效可讀指派再分頁；指定Project授權只讀該Project，本人指派也受同組ID限制。不按Owner／Manager名稱放行，readAny與role到permission映射沿用集中規則。
- `AccountService.context`：每次照常重驗帳號與有效成員，但只投影必要欄位，不附帶歷史或legacy_attributes。沒有新增權限TTL快取。
- Project v1屬性不再附全員memberIds。成員選項應獨立有界查詢，尚未實作；現有前端未改用此端點，不能把不完整摘要直接塞進編輯表單。
- 根／後端AGENTS、專案自有Laravel Architecture及API文件同步：原API契約可改版與退役，保留的是資料、安全及版本／防重複提交語意，整包回應形狀不是不可變要求。

## P1未完成與後續驗收

| 項目／卡片 | 現況與必須補上的證據 |
| --- | --- |
| Workspace與頁面分責 TT-180／214／215 | 後端邊界已建立，前端仍讀完整Workspace；須遷移列表、單卡與活動、選項及側欄依賴，避免編輯草稿／revision被背景資料取代，完成後退役過量入口。 |
| Todo TT-216／217／218 | Todos.listing仍先讀工作區todos／notifications／members再PHP過濾，converted links仍有逐項讀取；須參與者SQL條件＋分頁、通知摘要／清單獨立、內容按需與批次授權。 |
| 輪詢與事件 TT-146～149／219 | useWorkspaceClock仍30秒呼叫全量刷新，看板事件也連回此入口；須可見頁生命週期、局部同步、斷線備援與退避、撤權及草稿保護，量測多分頁背景請求。 |
| 寫入驗證 TT-221 | IssueService.validateLinks／子項處理仍有all(issues)，部分估時驗證讀all(timeEntries)；須限定Project／選取ID及必要聚合，保持交易／revision／requestId。 |
| 工時 TT-182／223 | 報表仍取期間明細後PHP聚合，月曆待按期間／Project／每日明細拆分；須SQL聚合、資料列成長／期間／權限交集與分頁測試。 |
| 鎖與重複上下文 TT-224／225 | Session鎖租期／排隊及其他重複資格讀取仍待完整處理。本輪只去除context多餘歷史，不能結案全部上下文工作。 |
| 量測門檻 TT-226 | 已補範圍成長與投影斷言，但遠端SQL RTT／掃描列數／多Session HTTP與背景請求基準仍缺，保持進行中。 |

其他既有runtime／DOM／bundle、receipt容量與長期觀測待辦也未因此結案。新規範對所有後續API／查詢／前端載入修改生效；不把「之後可接API」視為已接線。

## 驗證環境與限制

- 預設隔離memory SQLite：完整回歸71 passed／2716 assertions，7個原生MySQL案例明確skip。最後補強回應不隨其他Project增加的斷言，受影響ScopedReadApiTest＋ProjectAclTest共9 passed／414 assertions。
- 獨立Compose `tasktracker-perf-test`／`tasktracker_test` 真實MySQL：完整78 passed／2803 assertions，含原生JSON、LIKE搜尋、InnoDB交易及新投影。未對外部業務庫執行seed／RefreshDatabase／壓測；未清共享Redis。
- 本輪SQL限制／範圍證據不等於正式部署容量。外部MySQL與Redis分機、PHP runtime、網路RTT、暖冷快取、資料分布都影響HTTP；SQLite結果不得代替遠端MySQL或實際部署拓樸驗證。
- 前輪單次getWorkspace為437772 bytes／14.953s，僅正常使用觀察，非p95；與新DTO範圍不同，不能直接算加速倍率。未取得RUM、負載曲線與完整背景請求基準。

### 外部MySQL的正常讀取觀察

以AI帳號、既有資料、本機frontend代理→Laravel開發runtime→外部MySQL／Redis，登入後每端點只GET一次；未植入人造資料或並行壓測，快取冷暖未控制。記錄原始HTTP JSON bytes（非gzip）：

| 端點範圍 | 回應bytes | HTTP時間 | 筆數 |
| --- | ---: | ---: | ---: |
| 單Project屬性 | 1115 | 2567 ms | 1 |
| 指定Project任務摘要 perPage=20 | 10277 | 2314 ms | 20 |

此拓樸下延遲仍達秒級，不能因payload變小便結案。沒有SQL逐段計時，尚不能把延遲歸因於特定SQL／RTT／Session鎖／runtime；後續TT-224／225／226須分段量測、比較同範圍與同資料分布。樣本數1不能計算有意義的p95，也不能替代實際部署驗證。
