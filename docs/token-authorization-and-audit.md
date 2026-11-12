# Token授權、來源與撤權（TT-166）

2026-11-12。承接[個人PAT](personal-access-tokens.md)；公開業務查詢與寫入仍由TT-168／169接續，不把共用交易邊界當作已交付卡片REST。

## 共用邊界與資格交集

`BearerTokenSession.read/write`是公開HTTP的可信邊界。只取Authorization Bearer，不依Session、payload的user／role或目前網頁工作區。使用Sanctum `findToken`驗證既有雜湊，重新取得Token、User與Workspace資格；不使用Auth guard快取的上一個操作者。`TokenContext`只有當次WorkspaceContext、Token ID／名稱／abilities，不持有秘密或HTTP物件，不註冊帶使用者狀態的singleton。

- read需要read能力；write隱含read，但沒有超出本人權限的資格。
- 指定Project時沿用ProjectAccessFactory的有效permission＋scope檢查，不按角色名稱或Token有write直接放行。
- 具體業務端點還須呼叫既有Service，處理資源／作者、revision、requestId與receipt、afterCommit事件；本邊界不能取代這些條件。管理Token仍走Session／CSRF及WorkspaceTransaction，不容許Bearer替自己增發憑證。
- 帳號不存在、Workspace資格刪除／停用、Token撤銷或到期回401。現有帳號模型沒有獨立全站停用欄位；這裡的停用指Workspace成員停用，不憑空宣稱已新增全站帳號管理。
- 有效Token卻欠缺能力或已知Project的操作權回403；不存在、跨Workspace或已不可見Project回404。既有Session跨分頁工作區不符仍409；未實作的業務端點不會因本卡產生新的409契約。

## 撤權時序與交易

寫入先在交易外取得Token hint，僅用於定位User／Workspace鎖；hint不是授權結果。交易內依User→Workspace取得穩定鎖後，才重新查Token、到期／撤銷、User、Workspace資格與Project權限，隨後執行既有用例。Token的owner／Workspace與hint不符即拒絕。撤銷管理同樣遵守User→Workspace鎖序，因此等鎖中的舊Token請求會在鎖後看見已提交撤銷並回401。

「即時」指下一次用例重新驗證、不使用權限TTL cache，且寫入和授權變更依既有鎖序串行；不代表能取消已提交的業務或收回已回傳資料。已先取得鎖的寫入可先完成，撤銷隨後生效。唯讀請求使用該次交易快照，不將每個讀取都升級為寫鎖。停用／Project ACL變更沿既有Workspace鎖序，後續請求重建Context／ProjectAccess，不重用舊授權快照。

## 成功操作來源

`integration_audits`只追加成功寫入來源，與用例在同一交易提交，失敗一起回滾。每筆欄位：

| 欄位 | 意義 |
| --- | --- |
| userId | 真正執行的使用者識別 |
| source | session或token |
| tokenId／tokenName | Token識別與當次名稱快照；Session為null |
| abilities | 當次Token能力；Session為空陣列 |
| operation | 伺服器固定operationId，禁止用任意URL或payload取代 |
| resourceId | 已授權資源識別，不含描述、留言或其他業務內容 |
| occurredAt | ISO8601 UTC時間 |

現有已接線操作：PAT建立與首次撤銷記Session來源，重複撤銷不重複記錄。後續公開寫入必須經Bearer write邊界；它在用例成功後追加Token來源。本輪沒有修改既有卡片歷史或補造舊操作來源。一般讀取不寫audit、不更新last_used_at；不是全請求存取日誌，也不記拒絕請求或HTTP／密碼／Token原文、雜湊、Cookie、IP、email及payload。

User／Token識別刻意用快照、不設刪除級聯，保留刪除前來源；Workspace使用FK。無新增自動清理／TTL政策，表會隨成功寫入成長；不是防管理員竄改或法遵稽核系統。

## 本人來源清單 API

`GET /api/v1/personal-token-audits?page=1`，僅Session，建議帶X-Workspace-ID。回應仍為ok/data envelope；data為items、page、perPage=20、total、lastPage。id倒序、SQL依本人及目前Workspace過濾，超出頁數回空items。page須1～1000000整數，否則400；沒有資格401、跨分頁工作區不符409，其他使用者／其他工作區的紀錄不在清單。此版提供API，尚未製作專屬稽核UI。

正式機器規格與既有路由同份[OpenAPI](../backend/resources/openapi.json)，匿名入口 `/api/public/openapi.json`。Session讀取方式：

```sh
curl --fail-with-body -b "$COOKIE_FILE" \
  -H "X-Workspace-ID: $WORKSPACE_ID" \
  "$BASE_URL/api/v1/personal-token-audits?page=1"
```

Cookie檔須私有保存，範例不建立或輸出憑證；第三方PAT不能用此管理入口，公開業務API另依後續卡契約。

## API資料存取審查

1. 身分只需一個Token／User／Workspace資格；來源清單只需本人metadata，不讀Project卡片全集。
2. 清單固定20筆、id穩定倒序；權限與工作區條件在SQL執行。
3. SQL COUNT使用本人／工作區索引，深頁OFFSET仍隨頁數成長，不宣稱固定延遲；目前無cursor產品契約。
4. 不載整個Workspace、不補載歷史／留言／工時；Project授權只讀指定Project與該人的資格。
5. 單一來源紀錄欄位有限長度，Token能力為既有read/write；API不帶秘密。
6. 每次Bearer多做鎖後Token重驗；寫入增加一筆audit及Workspace ID查詢，唯讀不新增寫量。沒有新增輪詢、背景全量請求或Redis權限快取。
7. 測試包含不同使用者、同人不同Workspace、20＋4筆分頁、降權／停用及重用同一boundary的A→B→A，不只驗SQL數。
8. 本卡不等於整體效能或公開REST驗收，未完成端點／來源接線由TT-168／169續接；無HTTP p95／正式負載宣稱。

## 本輪驗證

- 隔離memory完整回歸：113 passed、40原生MySQL測試略過，4775 assertions。
- 原生MySQL PAT／來源／撤權：10 passed、259 assertions；最終受影響memory＋MySQL＋規格14 passed、353 assertions（再補稽核寫入後故障的完整回滾）。
- Chrome隔離HTTP通過Token建立一次、Bearer、重開不洩漏、撤銷401、Session來源與無秘密清單、三尺寸；沒有新增產品UI。
- Pint僅處理本卡PHP範圍；正式OpenAPI4個path／5個operation與本機公開JSON一致。
- 本機稽核migration batch3已執行，backend／queue／websocket已載入新版；主庫未seed／reset。

MySQL競態使用獨立PHP程序／PDO，主程序持有User→Workspace鎖且尚未提交撤銷，worker先讀舊Token後才等帳號鎖；撤銷提交後worker回UNAUTHENTICATED、無成功audit。fixture先提交至tasktracker_test專用庫，秘密僅存0600暫存fixture並finally清理，不接現用主庫。這是正確性證據，不是正式HTTP負載測試。
