# Project增量任務摘要（TT-170）

2026-11-12。新增`GET /api/public/v1/projects/{project}/issue-changes`，OpenAPI operationId `listPublicIssueChanges`，規格0.5.0。舊頁碼Issue列表與前端呼叫不改；本接口供外部整合接續讀取，不混入詳細、留言、工時或Workspace全集。

## 契約與授權

回`items／perPage／nextCursor／syncUntil`，預設20、最多100；沒有COUNT、total或OFFSET。沿Issue列表的type／state[]／priority／assignee／identifier／search／epicId／scope等SQL篩選，新增包含邊界的updatedSince（有時區ISO8601）；page禁止，只支援時間升冪。摘要投影及既有IssueListResource共用，不複製第二套資料模型。

固定本輪syncUntil，keyset按`updated_at,id`升冪，唯一ID處理同秒／同微秒；公開只回公開ID，不洩漏內部鍵。無日期歷史資料只在全量起點包含，排最前並可接續。每批SQL最多perPage+1以判斷續頁；索引`issues_change_cursor(workspace_id,project_id,updated_at,id)`只增加查詢能力，不改歷史資料或時間。

Cursor以Laravel認證加密，綁本人／Workspace／Project及完整標準化filters（含perPage、updatedSince）。不可自行解析／改字串；換條件重新起輪，無效400。每批先重驗PAT與目前Project issue.read；撤權404、失效401，不能用cursor取代授權。APP_KEY更換使舊cursor失效；同一人換有效PAT但範圍一致可續接。

先處理並保存本批，再保存nextCursor；只有nextCursor=null才保存syncUntil作下一輪**inclusive updatedSince**，依id／revision去重。Python公開下載範例已有完整接續迴圈，公開contract與Agent指南同步。

這是live資料列表，非跨HTTP交易snapshot。卡片同輪變更可能移動／重複；inclusive水位處理時間邊界，但不宣稱長交易延遲提交／倒填時間／刪除事件的無損同步。TT-173另定完整／增量同步及刪除／封存語意，不能以本卡取代。

## 資料存取與驗證

- 呼叫端只需單Project摘要，最新詳細／活動按需用既有端點。沒有前端輪詢或背景任務新增。
- Repository共用SQL篩選，明確workspace/project where、穩定seek及有限投影，Resource只用預載Project／status／assignee name。搜尋可能在限定Project掃描字串欄位，不把它宣稱全文搜尋索引。
- 人造1000筆無關Project資料下，驗僅本Project同秒5張Task、type／state／priority／assignee／updatedSince交集，三頁不重複或遺漏；每批limit3（perPage2）、無COUNT／OFFSET／description投影／legacy／留言／工時。
- 真MySQL EXPLAIN對相同Project／時間seek使用issues_change_cursor且無filesort；不是p95、正式容量或遠端RTT結論。有界筆數不代表固定bytes或延遲。
- 測同水位包含邊界、null歷史、變造cursor／換filters／perPage／Project、非法日期／大小／頁碼、資格移除／停用，不以SQL次數或只驗回應相同取代讀取範圍。

最終本地獨立CI：memory138通過／55原生類略過（7187assertions），MySQL18通過（654assertions），官方Schema／負向、下載py／sh、獨立客戶端10階段、文件Chrome15頁尺寸及鍵盤、真HTTP限流通過。PHPDoc必需範圍219檔／705方法零缺漏。CI文件瀏覽器固定5185，避免與其他Agent的5175測試衝突，皆只測隔離API13049。

共用工作區的TT-235提交6c15508已連帶包含本卡初版DTO／Service／Repository／Controller及migration；保留既有歷史，本卡提交補齊契約、實作修整與正式測試，不以TT-235提交代替本卡驗收。詳見卡片進度留言。
