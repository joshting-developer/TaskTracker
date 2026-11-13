# TT-282 描述貼圖與草稿

本階段只在 Issue 建立／編輯描述顯式啟用圖片上傳。留言和待辦沒有開放附件, 共用 MarkdownEditor 預設關閉上傳能力, 不因共用元件而擴大產品範圍

## 使用與資料保存

- PNG／JPEG／WebP 可由系統剪貼簿快捷鍵、拖曳或選檔加入；限制由當下 Project 的 limits API 取得, 不讓前端自行猜大小或格式
- 上傳以真正的瀏覽器上傳事件顯示進度, 每個編輯器最多兩個同時上傳, 每個草稿最多 20 個檔案；伺服器仍嗅探 bytes、驗像素／大小／配額
- 原始檔案只保留在元件記憶體, 不寫入 localStorage；每檔固定 requestId, 失敗重試沿用。只有回傳成功才在原游標書籤加入 attachment:UUID, 不插入 base64、外部 URL 或儲存 key
- 書籤會隨後續打字移動, 不用舊全文覆蓋新內容；輸入法組字結束後才插入。支援瀏覽器原生 undo, 文字貼上及既有格式工具維持
- 未完成／失敗的檔案會擋住儲存, 可以重試或取消；失敗保留描述。離開保護納入上傳草稿；取消未完成的請求, 伺服器可能已保留的 pending 依原有 24 小時 TTL 回收
- 建卡／改卡與描述內附件綁定共用原本的 Workspace 交易。先驗當下 issue.write、Project／Workspace／Issue、pending 上傳者及 TTL, 全部通過才綁定；任何失敗會連任務、編號與附件一起回滾, 重送不重複建立
- 已綁到其他 Issue、其他 Project／租戶、其他人的 pending、過期或已刪除附件不能藉描述綁定。從描述移除圖片不永久刪除歷史附件, 避免破壞舊內容

## 資料存取八項

1. 編輯器只取單 Project 上傳限制, 成功回單張安全 metadata；卡片仍用既有有界寫入結果
2. 每草稿最多 20 檔／兩個上傳, 描述最多 6000 字；綁定只取去重後最多 20 個 ID
3. Repository 用 Workspace／Project 與 whereIn 限定, 排序鎖定後一次更新 pending, 不以 PHP 掃整租戶
4. 不新增 getWorkspace／getTodos 或輪詢, 不預載其他專案圖片
5. 預覽沿用 TT-287 lazy 私有讀取, 不載入歷史／留言／工時
6. 每開啟或切換描述 Project 一次 limits, 每張 upload 一次、預覽按需；重試沿用同 key。無圖片的普通建卡不增加附件 SQL；未量測正式遠端 p95
7. MySQL／memory 驗證同交易回滾、request replay、其他 Project／Issue、其他上傳者、過期與版本衝突；真實 Chrome 驗上傳失敗不破壞文字、保存阻擋及重載結果
8. 只回報實測範圍, 不把元件 build 當正式部署；公開 PAT 附件端點與 Scope／OpenAPI 整理仍由 TT-296 處理

## 驗證

使用隔離 tasktracker-codex-perf MySQL／Laravel, API 13089、Vite 5291。瀏覽器走真正的 IssuePanel 與實際上傳／建卡／讀取介面, 並使用瀏覽器 Clipboard API＋平台貼上快捷鍵、File 拖放及選檔

證據保存在忽略的 artifacts/tt282, 不提交畫面、憑證或 Cookie。依 Josh 持續授權直接保存這批畫面基準, 仍需重拍比較與退化負例；此授權不是逐張人工檢閱的紀錄

### UI Skill 審查

- UI/UX 指引核對上傳進度、可重試錯誤與欄位關聯；失敗列使用 alert, 格式拒絕訊息連到描述欄位, 互動按鈕至少 44px
- 設計 inspector 三尺寸沒有橫向溢位或 console error, 但靜態全頁檢查會將既有紫色品牌／主按鈕陰影、原生抽屜陰影及被 modal 遮住的背景標題列為 findings。這些沒有由本卡新增, 背景被阻擋是原生 modal 的預期行為, 紫色依產品既有品牌保留
- 這不是全專案設計掃描通過。一般表單排版整理接既有 TT-288；本卡以實際抽屜操作、保存／取消、三尺寸與固定基準驗證貼圖功能

### 本階段結果

隔離 MySQL／memory 合併回歸 42 項、2336 assertions 通過；既有 Markdown 真實瀏覽器回歸、七個 PHP 檔案 Pint 及 frontend build 通過。描述貼圖實測 WebP 選檔、PNG 系統剪貼簿快捷鍵及 JPEG 拖曳, 另驗取消、SVG 拒絕、IME 延後插入與原生 undo

三尺寸畫面依持續授權保存基準後重新拍攝, 通過既有 threshold 0.02／maxDiffPixels 0, 無遮罩或抗鋸齒排除。刻意注入可見退化會失敗並移除舊成功摘要, 還原後再次通過。基準紀錄在 docs/visual-baselines/tt282-attachment-drafts.json, 並明記持續授權而非逐張人工檢閱

現用 DB migration／正式映像／正式 scheduler 或 Bucket 不在這次部署範圍, 沒有對現用資料 seed 或壓測
