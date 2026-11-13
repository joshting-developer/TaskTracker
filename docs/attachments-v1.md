# 私有Issue附件 v1

TT-191提供內部Session API及私有儲存基礎, TT-287接Markdown圖片顯示, TT-282接貼上／拖曳／選檔與建卡草稿。這次沒有開啟留言、Todo或公開PAT附件能力, R2部署另由TT-291處理

## 操作與資料

- `GET /api/v1/projects/{project}/attachments/limits`：可編輯Issue者取得前後端共用限制, 不回disk或key
- `POST /api/v1/projects/{project}/attachments`：multipart的`requestId` UUID與`file`；PNG／JPEG／WebP, 預設10MiB及2500萬像素, 嗅探bytes而非相信前端MIME。只接受相符副檔名, 不接受SVG、HTML、GIF或遠端URL匯入
- `POST /api/v1/projects/{project}/attachments/{id}/bind`：JSON含requestId、expectedRevision、issueId、issueRevision；pending只能由上傳者綁定同專案Issue。綁定與Issue revision／異動歷史同交易完成
- `GET /api/v1/projects/{project}/issues/{issue}/attachments?page=1`：有效metadata, 固定20筆、依內部ID穩定排序；不附binary或其他Issue附件
- `GET /api/v1/projects/{project}/attachments/{id}/content?download=true`：同源授權串流, 每次重驗Session／專案issue.read；pending僅上傳者。固定嗅探MIME、nosniff、no-store、same-origin與CSP sandbox, 不redirect公開bucket或外部URL
- `DELETE /api/v1/projects/{project}/attachments/{id}`：requestId與expectedRevision；已綁定另需issueId／issueRevision。pending限上傳者；已綁定附件由同Project具issue.write者操作

成功metadata僅id、name、mime、bytes、status、revision、reference、expiresAt。穩定引用為`attachment:<UUID>`, 不儲存短效URL、物件key、Base64或HTML。正式內部機器契約見[OpenAPI](contracts/attachments-v1.openapi.json), 不是public PAT OpenAPI

所有寫入保留Session／CSRF／X-Workspace-ID, 當下permission及同租戶／同Project範圍。bind／delete先授權再查receipt, 版本／簽章衝突409, 不可見／過期／已刪404, 儲存暫時故障502。綁定或刪除會增加Issue revision, 編輯器必須使用回傳的新版本而非沿用舊值

## 儲存與失敗恢復

上傳先在User→Workspace鎖的短交易保留`uploading`及配額, 交易外寫私有物件, 第二個短交易再重驗身分並轉`pending`。相同上傳者／Workspace／requestId只保留一筆, 重送需相同專案、檔名、MIME、bytes與checksum；失敗可用同requestId恢復, 不盲換UUID重建。其他內容重用requestId回409

物件IO不放在DB交易內。IO成功但DB finalize失敗時保留uploading, 可重送或等到期清理；不在catch直接刪檔, 避免另一個並行重試已成功時誤刪物件。Content先完成當下授權再於交易外開串流, 傳輸中的請求採該次已驗權限, 不宣稱能在撤權時追回已傳出的bytes

預設本人未綁定上限20張、每Issue20張、Workspace總實體保留量1GiB, 都可配置。配額包含uploading／pending／active／deleted／purging, 物件確實清除後才釋放bytes；縮小配額不會自動刪既有內容

pending／uploading預設24小時過期, active不自動到期。明確delete後立即停止下載, 物件保留30天再清理, 不因從Markdown刪引用就立即刪檔；刪除歷史metadata保留, 過期圖片在歷史顯示失效替代。仍有附件metadata的Issue拒絕永久刪除, 可改closed, 不以外鍵500回應使用者

`php artisan attachments:cleanup`每次最多1000個候選、每批100筆；先重驗過期並標記purging, 交易外delete, 成功後標記purged並清空key。失敗保留purging與key, 下次可重試。已排每小時schedule, 仍需部署環境真的運行Laravel scheduler才會自動執行；本卡沒有聲稱正式排程已啟動

## 配置、備份與部署

預設`attachments`為`storage/app/attachments`的private local disk, 不建立public storage symlink, serve=false。Docker開發環境已有laravel-storage持久volume；部署不使用Docker時也必須保留該目錄並確認寫入權限。備份須同時保護SQL metadata與各筆disk/key對應的物件, 只備份SQL不能還原圖片；還原時先核對checksum與bytes再開站

backend/.env.example列出配額與TTL。PHP upload_max_filesize=10M、post_max_size=12M, production Nginx請求上限12m；若調高應用上限, PHP／代理層也要同步。這是組態調整, 不代表正式映像已建置或部署

`r2-attachments`只預留S3相容disk及空白env, 未安裝AWS S3 Flysystem adapter、未連Bucket、未提供secret。TT-291確認相容套件與最小權限後才可選用；既有每筆disk會保留, 改ATTACHMENTS_DISK只影響新上傳, 不自動搬舊圖片。既有本地物件遷移由TT-292處理

## 八項資料存取審查

1. upload只取單專案資格、同requestId記錄及配額aggregate；bind／delete只取指定附件和Issue版本
2. Issue附件固定20筆且穩定ID排序；清理每批100、每次1000；limits回固定小物件
3. Workspace／Project／Issue／actor／status／過期條件在SQL或已限定實體上執行, 不載全Workspace再篩選
4. 不用Workspace快照或完整Issue內容作授權／版本驗證
5. binary只在content按需讀, list不讀Storage；留言／歷史不用於附件列表
6. 沒有輪詢, 寫入配額aggregate受Workspace鎖保護；配額為metadata SUM, 不對Bucket列物件。附件很多時仍有SUM成本, 不是宣稱零成本
7. 測試增加110筆其他Issue附件後, 目標Issue仍只1筆且回應小於1200bytes；測租戶／Project／pending actor／撤權／工作區切換、版本、配額及IO重試
8. R2、正式scheduler及部署未驗, 明列另卡；本卡不替代TT-287／282的產品畫面驗收

Storage與內容驗證按目前Laravel 13 API確認, 參考[Filesystem](https://laravel.com/docs/13.x/filesystem)與[檔案驗證](https://laravel.com/docs/13.x/validation#validating-files), 不另外加入供應商專用業務層

## 本卡驗證

- 隔離Compose tasktracker-codex-perf／MySQL8.4.13, memory與MySQL附件案例加刪卡／DI／公開寫入／前兩張效能改善回歸, 共48項2967assertions通過
- Storage fake驗14項附件安全／生命週期案例, 不連外Bucket；額外Playwright request真HTTP＋local private disk驗multipart、10MiB精確邊界與多1byte拒絕、bytes串流一致、MIME嗅探與安全標頭、相同requestId及跨租戶拒絕
- 110筆其他Issue附件不改目標列表範圍, 頁面固定20筆；IO／finalize／bind／cleanup故障注入驗可重試及整體回滾, 沒有只驗mock回傳
- 限定Pint、Nginx -t及Artisan命令註冊通過；內部OpenAPI沿既有私有venv與官方固定3.1 Schema離線驗證, 不新增依賴或改Schema
- 原始log在忽略artifacts/tt191, 圖片／binary、私有metadata、資料庫與憑證不進Git；尚未建立正式映像、套現用庫migration或啟動正式scheduler, 不把測試通過說成已部署
