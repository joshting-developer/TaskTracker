# 第三方API文件規劃：查重與補卡

日期：2026-11-12。依使用者要求比對同步後文件與現有250張卡。本輪只查重／建卡／補驗收與分類，未實作PAT、公開API、文件站或Agent連接器。

## 新增三項工作

| 卡片 | 獨立交付 | 狀態 | 主題 |
| --- | --- | --- | --- |
| [TT-252](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/55f47f50-cea4-486a-9f1f-f4c3ef1cf1f2?detail=1) | 提供第三方開發者文件入口、PAT與Agent指南及可執行範例 | open | AI整合/API |
| [TT-253](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/89dd9c95-d90c-48c6-9b94-c7b7d8370584?detail=1) | 建立OpenAPI與實際HTTP契約及文件範例的CI驗證 | open | AI整合/API |
| [TT-254](http://127.0.0.1:5174/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/issues/817ddf29-150a-404f-8cf2-e5633b947eab?detail=1) | 以僅讀公開文件與PAT的獨立客戶端驗收建卡流程 | open | AI整合/API |

三張均為Task、指派taskTrackerAI、歸TT-3 API專屬Epic。看板已明確收錄TT-3，新卡透過Epic動態收錄；建立時同時提供AI整合/API的boardId。以該看板issues端點的identifier精準查詢逐張讀回確認，不用歷史分類快照覆蓋現在的selectedIssueIds，也不因動態收錄而重複選取卡片。

## 為何需要另開

- TT-167已追蹤REST及OpenAPI機器契約，不重開同義規格卡；TT-252負責可閱讀的文件入口、認證／Agent指南及可執行範例，與機器契約分工。
- TT-226是資料範圍／背景請求／效能回歸，不包含公開OpenAPI的schema、安全方案、operationId、公開路由與範例防漂移；TT-253獨立追蹤這項CI交付。
- TT-177是特定工具自行提供adapter後的選配聯調，仍deferred；TT-254是第三方基礎階段必做、不讀原始碼的通用HTTP客戶端驗收，不等待MA-79或某個模型／插件。

TT-252涵蓋文件的發佈入口，不另開同義文件部署卡；未要求增加新常駐服務或自建MCP。實際URL／文件工具鏈在實作卡定案，規劃中的/docs等不當作已存在路由。

## 補強既有卡，不重建

| 卡片 | 本輪補充 | 保留狀態 |
| --- | --- | --- |
| TT-3 | 先效能／穩定性、再PAT／公開API／必要文件；以文件導向客戶端驗收 | open |
| TT-165 | read／write、write含read；綁單一Workspace、預設一個月／最長兩年／無永久、過期不延長且不主動通知 | open |
| TT-166 | 動態權限交集、撤權／停用／到期／撤銷即失效，稽核不保存Token原文 | open |
| TT-167 | 標題改「定義公開REST契約與必要OpenAPI機器規格」；單一可信正式契約／未登入可讀入口，Swagger UI仍選配 | open |
| TT-168 | 文件可說明的有界Project／狀態／單卡查詢、identifier精準定位、摘要與編輯快照分開 | open |
| TT-169 | 既有建立／更新／留言／關聯共用服務、revision／requestId／重試及同卡更新文件測試；不加刪除前置 | open |
| TT-177 | 清楚區分TT-254必要通用驗收及本卡選配工具聯調 | deferred |

新規則放在描述最上方，舊敘述保留沿革並標明相悖處以最新已確認規則為準。既有卡狀態與指派經更新回應核對保留，不以規劃補充推定功能完成。

## 分類與排程

以上新增工作都屬AI整合/API，沒有因文件提到公開入口而誤放SEO，也沒有把契約CI混為效能壓測。Default自動包含全部卡。效能／穩定性仍先行，三張新工作尚未動工，保持open；實際開工前才改inprogress。

## 驗證與記錄

- 分頁讀取250張摘要，讀相關單卡詳細；沒有使用整包Workspace查重。
- 建立後共254張：三張新工作及一張本輪審查交付TT-251；TT-251只追蹤查重／補卡／文件驗證，與未來功能交付分開。
- 從單卡詳細及AI看板有界精準查詢讀回新卡ID、open／指派／Epic與看板可見性；既有七卡保留原狀態及指派。
- [讀回快照](planning/api-documentation-gaps-2026-11-12.json)僅記建卡當下，審查卡當時inprogress；不是之後可重跑覆蓋人工進度的匯入腳本。
- 純規劃／資料操作，未修改產品程式、schema或部署，不額外跑build／應用全套測試；檢查本地文件連結、JSON及diff，不將正常API讀回說成正式效能驗收。

來源：[第三方文件規劃](third-party-api-documentation-plan.md)、[API-first決策](api-first-platform.md)、[分責查詢契約](query-api-v1.md)、本輪實際卡片及看板讀回。
