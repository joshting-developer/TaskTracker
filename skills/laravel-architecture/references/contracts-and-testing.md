# 契約、交易及測試

## TaskTracker可信邊界

- 操作者與active workspace由伺服器Session重新取得；有效permission ID＋Workspace／Project scope＋資源條件決定可見與可操作。roleId僅查權限映射，role名稱永遠不是放行條件。
- 保留Session／CSRF、跨分頁X-Workspace-ID比對與Session blocking；GET後的permission、作者、版本與狀態可能變動，寫入交易內必須重查。讀requestId重試結果前也要重查授權及scope。
- 保留HTTP／DomainError語意、ok/data、API欄位、revision與requestId。格式驗證放FormRequest，依賴當前資料的商業／跨記錄驗證仍在Service／Action。不得只用FormRequest.authorize取代寫入前重查。
- 日期採YYYY-MM-DD，工作日Asia/Taipei；時間戳ISO 8601。只修改本人有效工時與每日24小時限制仍有效。open／closed必要、只有closed結案，不把skill示例的狀態enum覆蓋自訂狀態。
- 不回傳SQL、exception原文、秘密或內部路徑；診斷只進受控log。輸入用allowlist，不做不受控mass assignment。

## 交易與副作用owner

- 協調整個寫入的Service或頂層Action擁有transaction。Repository不自行commit／rollback或包跨用例transaction；Model不偷偷開交易。多個Repository／Model更新、revision與receipt寫入要在同一邊界。
- 現有ApiExecution／RequestReplay的交易與重試機制維持為相容邊界；未來重構前先確認owner，不在每一層再套transaction。巢狀交易不能當成跨服務一致性方案。
- 檢查後寫入以鎖或expectedRevision條件更新防競爭；SQLite不能假定SELECT FOR UPDATE等同其他DB的行鎖，使用現行交易、版本與去重機制，不以換DB迴避問題。
- 整批原子或逐筆部分成功必須事先定義。預設維持現有原子契約；部分成功需逐筆結果、授權與rollback邊界，不擅自把批次失敗改成跳過。
- Queue／事件在commit後送出；外部IO不在長交易中同步執行。Job有timeout、重試／backoff與冪等性；SMTP仍只verify／preview，不因ECC郵件範例發真信。
- Reverb仍用私有Project頻道，只發無內容訊號；API重新讀取時重新授權，禁止把資源內容放進公開事件。
- cache key包含租戶與必要scope，權限敏感快取需撤權失效。ECC cache tags只用於支援的driver，不為規範更換目前driver。

## 每次新增或修改功能的測試決策

| 改動 | 必要證據 | Unit是否需要 |
| --- | --- | --- |
| HTTP CRUD／Request／Resource | Feature：合法與無效輸入、未登入／無權限／跨租戶、實際DB保存與response契約 | 純規則存在才加，不mock Eloquent湊Unit |
| Service／Action規則或純計算 | Unit：正常、邊界、拒絕分支；Feature：實際入口、授權及DB副作用 | 有獨立商業規則必須補／改Unit |
| Repository／Query／Model關聯 | DB Feature／integration：scope、查詢結果／排序、空資料、關聯與索引／FK影響 | 不把SQL查詢用mock Builder自我驗證；有純投影可Unit |
| Resource／DTO映射 | 輸出Unit（欄位、型別、null、空資料）＋HTTP Feature契約；Resource不得引發額外查詢 | 有非平凡映射時需要；純envelope沿用既有測試 |
| 多步寫入／receipt／revision | DB Feature：完整rollback、版本衝突、重複requestId、改payload、撤權後重試 | 純重試決策可Unit；真交易不能被mock取代 |
| migration／匯入 | 隔離SQLite Feature：舊資料、FK／索引、版本、冪等、失敗不污染、回退邊界 | 通常不需要額外Unit |
| Queue／事件／程序 | Feature：afterCommit、不提交不發、授權／事件內容；Job／程序測試timeout／失敗／retry／cleanup | 有獨立handler規則才加 |
| API契約影響Vue流程 | 真實隔離Laravel＋瀏覽器：權限、保存、衝突與草稿、手機相關路徑 | mock前端不可取代端到端 |
| 純skill／文件 | 來源、連結、symlink、規則一致性、diff | 不新增產品測試或操作業務DB |

不是每個新增class都要機械配一個Unit與一個Feature檔；依上表覆蓋真實風險，可以擴充既有測試檔。測試expected value不得呼叫被測的新算法生成，mock必須符合真實collaborator契約，禁止無語意assert、用skip掩蓋可準備的fixture或只驗mock自己回傳的值。

## 測試環境與執行

- 維持PHPUnit與現有TestCase。bootstrap前強制程序env及config為testing／memory SQLite，不只依賴phpunit.xml，也不因範例RefreshDatabase／DatabaseTransactions改用業務DB。
- fixture由每個case／明確共用setup建立，不依賴本機seed、其他測試先後或主鍵固定編號。新增class引用確認use／FQCN，至少執行經過該行的case，再跑完整修改測試檔。
- DB行為必須驗真DB，external IO可fake但仍驗必要呼叫。測試錯誤比對穩定業務契約，不硬比PHP／Laravel非業務exception wording。
- 指令：`docker compose exec backend php artisan test --do-not-cache-result`。先跑最小受影響子集作baseline，完成後跑完整受影響檔；只有新風險或共用改動才擴大，不無限重跑。
- 瀏覽器使用E2E_API_URL接隔離Laravel，不指主資料庫；Docker helper覆寫compose project／service labels。migration另比對外部sentinel，不能只看測試綠燈推論未動資料。
- 程序／測試容器由啟動者在finally／teardown清理，設定合理timeout，只清理本次可識別資源，不用全域pkill／廣泛刪檔。
- 測試未能執行時說明實際限制；lint不等於runtime成功，安裝skill不等於完成重構。
