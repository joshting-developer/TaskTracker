# Season需求核對與卡片索引

TT-367, 2026-11-15。此為正式卡片／程式核對結果, 不是下載交接原檔的副本。正式TaskTracker仍是進度來源

## 範圍與現況

- 開始前讀365張卡、8筆看板與24張相關卡全文, 保存私有開始清冊與逐筆requestId回執
- 原8筆看板全部保留, 本次封存0筆, 刪除0筆。TT-283／333已有封存清單／還原交付, 公開API目前沒有封存寫入, 不靠DB直寫
- Tags尚無公開端點與Issue模型契約, 本次實際套標0張；Season看板作暫時整理, 不冒稱已有Tags
- 新卡均open未指派, 舊卡保留承辦／Epic／工時, Claude持有的TT-193／186／209只補需求, 不搶領
- GPT私人整合TT-350／351暫停等待joshting.1999及明確恢復；先前帳號歸屬推定撤回, 不操作Plugin／PAT／Secret
- 5174尚未恢復, TT-370追蹤。5199／13199已用隔離MySQL驗證本批, 原本機資料庫仍是唯讀備份

## 已有交付對照

| 需求 | 沿用卡片 | 核對結果 |
| --- | --- | --- |
| S0-01 管理後台 | TT-364 | 實作與隔離驗證完成, v0.5.0獨立管理登入／真實統計／三尺寸／匿名及一般帳號拒絕已通過 |
| S0-02 圖示 | TT-362／363 | 資產已交付, HTML及runtime引用已驗, 未宣稱真實手機安裝完成 |
| S0-03 多語系 | TT-308／365／366 | 既有登入App＋新增公開頁, v0.5.0同批發布並完成正式切換／reload／三尺寸驗收 |
| S0-04 私人GPT | TT-348／349／350／351 | 可保留基礎程式, 帳號／安裝／真實查卡未驗收, 暫停 |
| S2-04 封存還原 | TT-283／333 | 已closed且本批board-archive真實回歸通過, 不重做 |
| S2-01 Issue／留言附件 | TT-191／282／287／293／295 | 已交付, 保留原功能；紙條接線仍缺TT-294 |

## 需求矩陣

| 需求鍵 | 卡片 | 狀況與處理 | 主要Season |
| --- | --- | --- | --- |
| S0-05 | TT-368 Issue支援計畫開始與結束日期, 保留獨立到期日 | 查無對應未完成範圍, 新建待辦 | Season 0 |
| S0-06 | TT-193 候選：批次修改與標籤管理 | 沿用舊卡補需求／保留承辦 | Season 0 |
| S0-07 | TT-369 調查紙條與活動紀錄的跨日時間顯示異常 | 查無對應未完成範圍, 新建待辦 | Season 0 |
| S0-08 | TT-370 恢復5174本地隔離預覽與可重現的啟停流程 | 查無對應未完成範圍, 新建待辦 | Season 0 |
| S1-01 | TT-371 對外品牌更名為Pavenro並保留既有入口相容 | 查無對應未完成範圍, 新建待辦 | Season 1 |
| S1-02 | TT-372 撰寫Pavenro品牌起源與可信產品定位 | 查無對應未完成範圍, 新建待辦 | Season 1 |
| S1-03 | TT-373 整理已交付功能的優勢與使用情境 | 查無對應未完成範圍, 新建待辦 | Season 1 |
| S1-04 | TT-374 保留現有介紹版型並擴充品牌故事與FAQ | 查無對應未完成範圍, 新建待辦 | Season 1 |
| S1-05 | TT-375 拆分Cloudflare Pages介紹站與Fly同源工作區 | 查無對應未完成範圍, 新建待辦 | Season 1 |
| S1-06 | TT-376 建立Cloudflare Pages獨立自動發布與回退 | 查無對應未完成範圍, 新建待辦 | Season 1 |
| S1-07 | TT-377 建立雙語靜態SEO網址與安全的舊路由遷移 | 查無對應未完成範圍, 新建待辦 | Season 1 |
| S1-09 | TT-378 工作空間限制五位有效成員並處理並行加入 | 查無對應未完成範圍, 新建待辦 | Season 1 |
| S1-10 | TT-379 集中公開站與工作區Origin設定以保留網域遷移彈性 | 查無對應未完成範圍, 新建待辦 | Season 1 |
| S1-08 | TT-209 選配：公開頁搜尋收錄與量測 | 沿用舊卡補需求／保留承辦 | Season 1 |
| S2-01-core | TT-380 盤點並統一長文字編輯器與附件操作契約 | 查無對應未完成範圍, 新建待辦 | Season 2 |
| S2-01-notes | TT-294 第二階段設計小待辦圖片附件的隱私與轉卡流程 | 沿用舊卡補需求／保留承辦 | Season 2 |
| S2-02 | TT-381 提供個人Dashboard的本人工作摘要 | 查無對應未完成範圍, 新建待辦 | Season 2 |
| S2-03 | TT-186 候選：首頁個人小工具與卡片篩選小清單 | 沿用舊卡補需求／保留承辦 | Season 2 |
| S2-05 | TT-382 時程與人員排程 | 查無對應未完成範圍, 新建待辦 | Season 2 |
| S2-05-data | TT-383 建立時程三視圖的有界查詢與共用互動契約 | 查無對應未完成範圍, 新建待辦 | Season 2 |
| S2-05-people | TT-384 提供以成員為列的人員排程視圖 | 查無對應未完成範圍, 新建待辦 | Season 2 |
| S2-05-week | TT-385 提供跨日任務的週曆視圖 | 查無對應未完成範圍, 新建待辦 | Season 2 |
| S2-05-gantt | TT-386 提供Project與Epic層次的專案甘特視圖 | 查無對應未完成範圍, 新建待辦 | Season 2 |
| FUTURE | TT-387 尚未安排Season的發想 | 查無對應未完成範圍, 新建待辦；暫不執行 | Future Backlog |
| F-WEBHOOK-OUTBOX | TT-174 建立交易一致的Webhook事件與outbox | 沿用舊卡補需求／保留承辦；暫不執行 | Future Backlog |
| F-WEBHOOK-DELIVERY | TT-175 實作Webhook簽章、退避、死信與手動重送 | 沿用舊卡補需求／保留承辦；暫不執行 | Future Backlog |
| F-PUBLIC-GPT | TT-352 規劃多人MCP的OAuth授權與撤銷契約 | 沿用舊卡補需求／保留承辦；暫不執行 | Future Backlog |
| F-API | TT-388 後續公開API擴充的剩餘能力盤點 | 查無對應未完成範圍, 新建待辦；暫不執行 | Future Backlog |
| F-INTEGRATIONS | TT-389 第三方整合連線與授權管理中心 | 查無對應未完成範圍, 新建待辦；暫不執行 | Future Backlog |
| F-PERF | TT-390 大型看板效能的後續量測與改善 | 查無對應未完成範圍, 新建待辦；暫不執行 | Future Backlog |
| F-FREEMIUM | TT-391 評估個人免費與團隊付費的商業模式 | 查無對應未完成範圍, 新建待辦；暫不執行 | Future Backlog |
| F-BILLING | TT-392 訂閱與計費系統的未排期規劃 | 查無對應未完成範圍, 新建待辦；暫不執行 | Future Backlog |

## 看板與後續

| 看板 | ID |
| --- | --- |
| Season 0 | 527d086e-284c-4aa8-86b2-6d858a3afd2e |
| Season 1 | 509bb500-5ee5-4e20-8667-04eb72d950df |
| Season 2 | 3d88696d-d675-4f82-9059-8d9aafeb53c8 |

Future總覽TT-387, 用子卡收新候選, 舊Webhook／公開GPT卡以關聯保留原Epic, 不重設層級。Future未收進Season 0／1／2

共用task-tracker-usage已補暫不執行／特定帳號等待的自動領卡排除, commit703b910, 僅本機提交未push；沒有在專案另複製Skill。Tags上線後須先讀定義description再解讀卡片, 非ACL與任意命令來源

Josh補標核對紙條已建立並讀回收件人Josh, ID `975db489-4b15-4a70-a220-04638760d8ad`。Season 0／1／2卡片列表分別讀回14／10／11筆；32個需求映射已逐卡讀回, 所有Future卡均有暫不執行文字。舊看板清冊8筆全部保留, 封存0筆。S1按品牌／內容／SEO規格→靜態介紹與hostname→舊路由／搜尋量測→五人限制依賴執行, 不開發S2或Future

## 最終狀態核對

| 需求鍵 | 卡號 | 讀回狀態 | 承辦 |
| --- | --- | --- | --- |
| S0-05 | TT-368 | open | 未指派 |
| S0-06 | TT-193 | deferred | 保留原承辦 |
| S0-07 | TT-369 | open | 未指派 |
| S0-08 | TT-370 | open | 未指派 |
| S1-01 | TT-371 | open | 未指派 |
| S1-02 | TT-372 | open | 未指派 |
| S1-03 | TT-373 | open | 未指派 |
| S1-04 | TT-374 | open | 未指派 |
| S1-05 | TT-375 | open | 未指派 |
| S1-06 | TT-376 | open | 未指派 |
| S1-07 | TT-377 | open | 未指派 |
| S1-09 | TT-378 | open | 未指派 |
| S1-10 | TT-379 | open | 未指派 |
| S1-08 | TT-209 | deferred | 保留原承辦 |
| S2-01-core | TT-380 | open | 未指派 |
| S2-01-notes | TT-294 | open | 未指派 |
| S2-02 | TT-381 | open | 未指派 |
| S2-03 | TT-186 | deferred | 保留原承辦 |
| S2-05 | TT-382 | open | 未指派 |
| S2-05-data | TT-383 | open | 未指派 |
| S2-05-people | TT-384 | open | 未指派 |
| S2-05-week | TT-385 | open | 未指派 |
| S2-05-gantt | TT-386 | open | 未指派 |
| FUTURE | TT-387 | open | 未指派 |
| F-WEBHOOK-OUTBOX | TT-174 | deferred | 保留原承辦 |
| F-WEBHOOK-DELIVERY | TT-175 | deferred | 保留原承辦 |
| F-PUBLIC-GPT | TT-352 | open | 未指派 |
| F-API | TT-388 | open | 未指派 |
| F-INTEGRATIONS | TT-389 | open | 未指派 |
| F-PERF | TT-390 | open | 未指派 |
| F-FREEMIUM | TT-391 | open | 未指派 |
| F-BILLING | TT-392 | open | 未指派 |
