# TaskTracker HTTP API 契約 v1

HTTP契約v1；規格版號以OpenAPI的info.version為準；更新：2026-11-12。正式機器契約：[OpenAPI](/api/public/openapi.json)。本文是版本／使用規則，OpenAPI是唯一端點及Schema來源；不另維護一份規格。本文與規格可匿名取得，不代表業務資料可匿名存取。

## 已開放能力與入口

Base URL是你實際部署TaskTracker的origin。公開Bearer前綴為`/api/public/v1`，目前提供身分、可見專案／成員／狀態、任務摘要／單卡詳細及留言查詢。不要以PAT呼叫Session RPC。已提供Issue建立／PATCH／留言／永久刪除寫入、刪除墓碑查詢與外部識別查詢／upsert／批次；不提供封存、MCP或工具專屬介面。

| Method／path | 認證／用途 | 限流 |
| --- | --- | --- |
| GET /api/public/v1/me | Bearer PAT；固定Token Workspace的本人識別及能力 | 每client IP 120次／60秒 |
| GET /api/public/v1/projects | Bearer；可見Project摘要分頁 | 公開v1共用IP配額 |
| GET /api/public/v1/projects/{project} | Bearer；Project詳細、狀態及本人有效permissions | 同上 |
| GET /api/public/v1/projects/{project}/members | Bearer；有效成員id／name候選分頁，不帶email | 同上 |
| GET /api/public/v1/projects/{project}/statuses | Bearer；排序的狀態id／label／color，設定上限20 | 同上 |
| GET /api/public/v1/projects/{project}/issue-changes | Bearer；updatedSince與有界cursor增量摘要，無COUNT／OFFSET | 同上 |
| GET /api/public/v1/projects/{project}/issues | Bearer；SQL篩選的任務摘要分頁，identifier可精準查卡號 | 同上 |
| GET /api/public/v1/projects/{project}/issues/{issue} | Bearer；單卡詳細／revision與有限關聯，無活動全集 | 同上 |
| GET /api/public/v1/projects/{project}/issues/{issue}/comments | Bearer；該卡留言分頁 | 同上 |
| GET /api/public/v1/projects/{project}/issues/external/{source}/{externalId} | Bearer；以外部識別取得單卡詳細 | 同上 |
| POST /api/public/v1/projects/{project}/issues | Bearer write；建立卡片 | 公開v1共用IP配額 |
| PATCH /api/public/v1/projects/{project}/issues/{issue} | Bearer write；expectedRevision保護的部分更新 | 同上 |
| POST /api/public/v1/projects/{project}/issues/{issue}/comments | Bearer write；新增留言並遞增revision | 同上 |
| PUT /api/public/v1/projects/{project}/issues/external/{source}/{externalId} | Bearer write；外部識別upsert，已存在時需expectedRevision | 同上 |
| POST /api/public/v1/projects/{project}/issues/external-batch | Bearer write；最多50項批次upsert，逐項回報 | 同上 |
| GET /api/v1/personal-tokens | Session；本人目前Workspace憑證metadata | 本版未設路由限流 |
| POST /api/v1/personal-tokens | Session／CSRF；建立憑證、一次顯示原文 | 每使用者20次／60秒，未登入以IP計 |
| POST /api/v1/personal-tokens/{token}/revoke | Session／CSRF；撤銷自己的憑證 | 每使用者60次／60秒，未登入以IP計 |
| GET /api/v1/personal-token-audits | Session；本人目前Workspace成功寫入來源 | 本版未設路由限流 |

匿名發現入口`GET /api/public/openapi.json`回傳OpenAPI物件，`GET /api/public/contract.md`回傳本文Markdown；它們沒有業務envelope、不建立Session、不讀業務資料。其他操作預設回JSON。

## 紙條（TT-311）

工作空間層級, 不在Project底下：

| 方法與路徑 | Token | 用途 |
| --- | --- | --- |
| `GET /api/public/v1/todos` | read | `scope=mine`（交辦給我, 預設）、`sent`（我交辦出去）或 `involved`（我經手的）, `state=open／closed`, 分頁最多100 |
| `GET /api/public/v1/todos/{todo}` | read | 單筆含完整內容；建立人、目前承辦人與曾經手者可讀；非經手者回404 |
| `GET /api/public/v1/todo-assignees` | read | 可交辦對象, 只回 `id`、`name` |
| `POST /api/public/v1/todos` | write | 建立；`title` 或 `content` 擇一, `assigneeId` 省略時是自己的紙條 |
| `PATCH /api/public/v1/todos/{todo}` | write | 交辦人修改內容 |
| `POST /api/public/v1/todos/{todo}/complete`、`/reopen`、`/return` | write | 承辦人完成、重開已完成的、退回上一手（可附 `reply`, 舊 `note` 仍可用） |

寫入都要 `requestId`（UUID）, 除了建立都要 `expectedRevision`；成功只回 `{id, revision}`, 要最新內容另外GET。同requestId重送回第一次的結果。錯誤代碼：400 INVALID_INPUT、401 UNAUTHENTICATED、403 FORBIDDEN（Token只有read、或不是可以做這個動作的人）、404 NOT_FOUND、409 REVISION_CONFLICT／REQUEST_CONFLICT、429 RATE_LIMIT。

## 我的通知（TT-315）

工作空間層級, 只回寄給Token本人的任務通知：

| 方法與路徑 | Token | 用途 |
| --- | --- | --- |
| `GET /api/public/v1/notifications` | read | 依產生順序遞增；`limit` 1–100（預設50）, `after` 帶上次的 `nextAfter`；回 `{items, nextAfter, hasMore}` |

每筆是 `id`、`kind`、`subject`、`projectId`、`issueId`、`identifier`、`createdAt`, 不帶正文和收件Email。`kind` 有 general、assigned、epic、comment、status、status_important（改成已關閉或待驗收）。本人現在不能讀該專案（issue.read）的通知會跳過, 所以一次可能少於 `limit` 筆, 但 `nextAfter` 照樣往前。網頁目前沒有任務通知的已讀狀態, 所以這裡沒有未讀篩選或標記已讀；交辦給自己的紙條請讀 `/todos?scope=mine`。錯誤代碼：400 INVALID_INPUT、401 UNAUTHENTICATED、403 FORBIDDEN、429 RATE_LIMIT

## 認證、Scope與工作區

使用`Authorization: Bearer <PAT>`；不要放query、URL或log。PAT由使用者在帳號選單「個人 API Token」建立，原文只呈現一次，伺服器只保存雜湊；一個PAT綁定一個Workspace。預設一個月（月底no-overflow），最多兩年，無永久／延期；指定日期在Asia/Taipei當日00:00到期。撤銷、到期或資格失效回401。

`read`僅讀取；`write`包含read，但實際可做的事仍是Token能力∩使用者當下permission∩Workspace／Project與資源／作者條件。Role只是權限映射。更換網頁目前Workspace或送X-Workspace-ID不會改變PAT綁定，不接受用payload自稱的actor／role取得資格。Bearer入口不接受Session替代、不需要Cookie或CSRF。

Session管理沿用網頁登入與CSRF機制；變更操作需符合Laravel可信同源或有效CSRF驗證，非瀏覽器管理工具應傳X-CSRF-TOKEN。建議帶X-Workspace-ID防止跨分頁切換後誤操作，與Session不符回409。PAT不能增發或管理另一個PAT，也不能取得其他使用者的Token或來源紀錄。

本版不啟用跨站瀏覽器CORS。帶不受信任Origin或Sec-Fetch-Site: cross-site會403；同源前端與具有連線權限的伺服器／CLI使用標準HTTP。取得文件並不讓外部工具自動取得網路能力或操作授權。

## 回應、錯誤與資料上限

業務成功為`{"ok":true,"data":...}`；失敗為`{"ok":false,"error":{"code":"...","message":"..."}}`。用error.code判斷分支，不依賴可能調整的繁體中文訊息。資料時間戳為ISO8601 UTC；工作日／到期日語意另採Asia/Taipei。客戶端應容忍未知回應欄位，不能把列表metadata當可編輯的完整資料。

| HTTP | error.code | 處理 |
| --- | --- | --- |
| 400 | INVALID_INPUT | 修正JSON、欄位、日期或page；不是422 |
| 401 | UNAUTHENTICATED | 停止重試，檢查Token／到期／撤銷／成員資格或Session |
| 403 | FORBIDDEN | 停止重試，檢查來源、Scope與有效權限 |
| 404 | NOT_FOUND | 不存在或不可見；也用於未開放路徑／版本 |
| 405 | METHOD_NOT_ALLOWED | 改用受支援method；Allow列支援方法 |
| 409 | WORKSPACE_CHANGED／REQUEST_CONFLICT | 依錯誤分支處理，不盲目產生另一個requestId |
| 419 | CSRF_EXPIRED | 僅Session變更；重新取得有效Session／CSRF後核對原請求 |
| 429 | RATE_LIMIT | 至少等待Retry-After秒再重試 |
| 500 | INTERNAL_ERROR | 公開Bearer非預期錯誤，回應不含stack／SQL／秘密；稍後核對後再試 |

每個端點可出現的業務錯誤以OpenAPI該operation為準；404／405也可能在路由分派時發生。公開更新／留言的expectedRevision不符回409 REVISION_CONFLICT；同requestId異內容為409 REQUEST_CONFLICT。

Token與來源清單只讀本人目前Session Workspace，固定每頁20筆、id倒序。page為1～1000000整數，預設1，不接受perPage。**Token清單超出範圍回最後一頁；來源清單超出範圍回空items**。data包含items/page/perPage/total/lastPage。COUNT／OFFSET仍會隨資料量成長，沒有固定延遲承諾；不需要定期刷新清單。Token建立需最多50000 bytes的JSON物件，未知欄位忽略且不授予任何權限。

## 公開查詢的範圍與篩選

Project／成員／Issue／留言分頁皆為page（預設1，上限1000000）、perPage（預設20，上限100）；超出最後頁回空items。Project與成員可search（最長200字），SQL處理字面%／_。Project依updatedAt降序、id穩定排序；成員依user ID；留言依建立順序與穩定id。狀態依Project設定排序，自訂type不可硬編成固定enum；open／closed必備。

Issue列表接受identifier（精準卡號，上限100）、state[]=open&state[]=closed（最多30項，每項40字）、priority（urgent/high/normal/low）、type（epic/task/bug）、assignee（User ID）、epicId（Epic ID或unlinked）、scope（all/mine）、condition（overdue/unassigned）、sort（updated/created/priority/due）、direction（asc/desc；省略時updated／created新到舊, priority緊急到低, due近到遠, due反向時沒填到期日的排最前）。欄位缺省不篩選，sort預設updated；同排序值用穩定ID決勝。條件在SQL執行，不先載入Workspace。未知query忽略，不會改變可信使用者／工作區。

identifier查詢仍是摘要列表；得到Issue ID後再GET詳細取得最新revision。詳細提供description、epicId、parentIssueId、relatedIssueIds與references；最多200個關聯，超限409 CONFLICT而非靜默截斷。references最多包含兩個上層參照與200關聯。estimateMinutes在Epic為所屬卡估時加總；spentMinutes僅具工時讀取資格時回傳。列表不回描述／關聯／活動或工時。留言獨立分頁，不在詳細附完整comments／history。

公開Project回傳本人的有效permission，這不是承諾Token可做所有permission：仍要和read／write交集。成員候選含擁有全專案可見權限者及有效Project成員，只回id/name。Session管理讀者與Token讀者共用相同Service／Resource；本版沒有公開歷史或工時明細端點。

## 限流與重試

Session建立／撤銷各自計數；公開v1所有讀取操作共用client IP配額，因此同IP多個工具與不同公開查詢合計共享120次／60秒；Token管理依登入使用者計數，不依Token原文作cache key。失敗請求也可能消耗配額，429可先於認證錯誤發生。反向代理必須正確設可信代理，不能信任任意X-Forwarded-For偽造IP。

受限操作的成功回應提供X-RateLimit-Limit與X-RateLimit-Remaining；429另提供Retry-After（秒）及X-RateLimit-Reset（Unix秒），保留JSON錯誤envelope。其他失敗不保證帶剩餘配額Header；沒有公開負載／可用性SLA。不要以併發或換Token規避限流。

- GET可在暫時網路錯誤後有限重試，採退避；401／403不自動重試。
- PAT建立必填UUID requestId。重試同一requestId若已成功，回409 REQUEST_CONFLICT，**不重播原文、不再建立一個Token**。逾時後先核對清單並撤銷可能建立的憑證，再由使用者明確決定新請求；不要自動換UUID重送。
- 撤銷沒有requestId／revision欄位；重試同一Token是200，不重複來源紀錄。Token不存在／非本人／不屬於目前Workspace回404。
- 不將timeout當作失敗未寫入。公開卡片寫入沿requestId原結果receipt及expectedRevision規則，操作成功的首次結果與之後最新資源可能不同，需GET核對。

## 版本與棄用

URL的v1是API主版本；OpenAPI info.version是這份契約的發布版號，與OpenAPI標準版本3.1.0不同。相容新增端點或欄位可在同主版本更新文件；刪除／更名必填欄位、改變資料或冪等語意等破壞性變更需另開主版本或明確遷移流程。未知版本404，不回退到另一版本。

本版沒有棄用中的公開操作，因此不發Deprecation／Sunset Header。未來棄用時需在同份OpenAPI標deprecated、公布替代操作與停止日期，並在已公告日期前保留原版本；日期與支援期間由實際發布決定，不憑空承諾固定天數。本機開發版不代表正式部署或SLA。

## 可執行的目前最小範例

只將BASE_URL設為你自己的部署origin、TASKTRACKER_TOKEN設為私有環境值。以下不需要Cookie，也不會輸出Token：

```sh
curl --fail-with-body "$BASE_URL/api/public/openapi.json"
curl --fail-with-body "$BASE_URL/api/public/contract.md"
curl --fail-with-body -H "Authorization: Bearer $TASKTRACKER_TOKEN" \
  "$BASE_URL/api/public/v1/me"
```

OpenAPI中的Token字串是明確的假值，不可登入使用。規格與本文不內嵌真實帳號、Cookie、內部主機或PAT。

查詢範例（先用Project列表取得PROJECT_ID，再用卡號精準定位）：

```sh
curl --fail-with-body -H "Authorization: Bearer $TASKTRACKER_TOKEN" \
  "$BASE_URL/api/public/v1/projects?perPage=20"
curl --fail-with-body --get -H "Authorization: Bearer $TASKTRACKER_TOKEN" \
  --data-urlencode "identifier=$ISSUE_IDENTIFIER" \
  "$BASE_URL/api/public/v1/projects/$PROJECT_ID/issues"
curl --fail-with-body -H "Authorization: Bearer $TASKTRACKER_TOKEN" \
  "$BASE_URL/api/public/v1/projects/$PROJECT_ID/issues/$ISSUE_ID"
curl --fail-with-body -H "Authorization: Bearer $TASKTRACKER_TOKEN" \
  "$BASE_URL/api/public/v1/projects/$PROJECT_ID/issues/$ISSUE_ID/comments?perPage=20"
```

## 公開寫入

三個寫入操作均須JSON物件（最多50000 bytes）與UUID requestId，更新／留言另需expectedRevision（**JSON整數**）。身份／Workspace與操作以伺服器路徑及Bearer為準；未知欄位忽略，不能用payload另指定id／projectId／actor／author。成功HTTP200，data只回id、projectId、identifier、revision；不回全部留言／歷史。最新資料及留言以GET另讀，防止寫入回應與receipt無限成長。

建立需title（180字內），其他可用description（6000字）、state（Project實際type，預設open）、priority（預設normal）、type（epic/task/bug，預設task）、assignee（有效成員User ID或空字串）、dueDate（YYYY-MM-DD或空字串）、estimateMinutes（0～525600整數）、epicId／parentIssueId（同Project有效卡或空字串）、relatedIssueIds（最多200個不重複ID）。不接受null清除，請用空字串或空陣列。PATCH只改提供欄位，省略保留；空relatedIssueIds明確清空關聯，禁止把列表摘要當整張卡片覆寫。留言需content（2000字內），作者為Bearer使用者，不能偽造。

父子關係一層、Epic可選與雙向關聯沿Web規則；子卡跟隨父卡Epic，無前置／DAG或結案阻擋。跨Project或非法層級整批rollback，不產生成功receipt／來源。相同使用者、Workspace、requestId及operation／有效輸入回原結果，不重複卡片／留言／通知／來源；不同有效內容409。Session與公開操作名分開，因此同key誤用另一接口會衝突；不提供跨接口自動重試。重播前仍重驗Token、Scope、Project與資源資格，撤權後不可讀既有結果。首次失敗完整rollback，可修正後再送；逾時則先沿同key重試核對，不任意換UUID。

取得單卡最新revision後，再PATCH狀態；遇409 REVISION_CONFLICT先GET重讀並決定是否重試，不能自動強制覆蓋。相關卡片變動也可能遞增其他卡revision，後續應各自重讀。結案請用closed狀態；永久刪除（DELETE，需issue.delete）只用於誤建等情況，有工時或子卡時會409，沒有封存。

## 外部識別與upsert

外部系統可用Project範圍的`source`＋`externalId`對應一張卡，不必解析描述或保存requestId。source為小寫英數與`._-`（最多40字，例如github、jira），externalId為`A-Z a-z 0-9 . _ ~ : @ -`（最多200字、區分大小寫、不含斜線；其他格式請自行對應或雜湊）。同Project內一組識別只對應一張卡；不同Project或Workspace可使用相同識別，互不影響。一張卡每個source只綁一個externalId。

`PUT .../issues/external/{source}/{externalId}`：未綁定時建立新卡並綁定（title必填，其餘欄位同建立）；已綁定時更新同一張卡，欄位語意同PATCH，**必須帶expectedRevision**，省略或過期回409 REVISION_CONFLICT，先`GET`同路徑取得最新revision再決定。建立時提供的expectedRevision忽略。成功data回id、projectId、identifier、revision、externalSource、externalId與created（true表示本次新建）。requestId仍只負責單次冪等：同內容重送回原結果，異內容409 REQUEST_CONFLICT；它不是長期識別，每次新的變更要用新UUID。

`GET`同路徑回完整單卡（同單卡GET）。格式不符、未綁定或無權一律404。寫入在User→Workspace鎖內序列化，唯一索引保證同識別不會建出兩張卡。綁定目前不可解除或改綁，也沒有依外部識別刪除的操作。

## 批次匯入與逐項結果

`POST .../issues/external-batch`一次最多50項（整個JSON仍受50000 bytes限制），依items順序處理。每項語意同單筆upsert：未綁定建立（title必填）、已綁定需expectedRevision。每項各自回滾：某項失敗只撤銷該項，HTTP仍200，data.items逐項列出index、externalSource、externalId、status（created／updated／exists／failed）及成功時的id／projectId／identifier／revision，失敗時的error.code／message；data.summary為各狀態數量。

- **Epic與子卡**：用`epicRef`或`parentRef`（`{source, externalId}`）引用外部識別，可指向同批前面的項目或既有綁定，不能和epicId／parentIssueId同時使用。建議先送Epic批次，再送子卡批次；引用不到的項目回NOT_FOUND，其他項照常。
- **逾時判定**：整批綁同一requestId。回應遺失時用同requestId與完全相同內容重送，會拿回原本的逐項結果，不重複執行；內容不同409 REQUEST_CONFLICT。
- **重跑不重複**：`createOnly: true`時已綁定項目回exists、不更新，所以修正失敗項後可換新requestId整批重送，已建立的卡不會重複。
- **結構錯誤**：超過50項、空items、同批重複外部識別、Ref與ID同時指定或型別不符，整批400且不寫入。

整批只追加一筆來源稽核（operation `public.v1.batchUpsertIssues`）。

## 增量摘要與cursor

`GET /api/public/v1/projects/{project}/issue-changes`回items／perPage／nextCursor／syncUntil（不回total／page）。預設20，最多100；預設按updated_at升冪（direction=asc）、唯一內部鍵打破同秒／同微秒平手。direction=desc改成從最新往回讀, 只適合「看最近改了什麼」, 不適合拿來做增量同步；cursor記住當初的方向, 換方向拿舊cursor續讀回400。回應仍只用公開ID。updatedSince接受有時區ISO 8601，包含邊界；不傳則全量起點，含無日期歷史卡片。type／state[]／priority／assignee等篩選沿Issue列表。只支援時間升冪，不接受page或其他sort。

保存上一批nextCursor後重送，須沿用原完整filters／updatedSince／perPage；syncUntil在同一輪固定。Cursor為不透明認證加密字串，不自行解析，URL請encode；變造、換本人／Workspace／Project／條件會400，無Project資格仍404／失效PAT401。Cursor不保存權限，每頁重新驗證；換Token但仍同本人及範圍可接續。APP_KEY變更使舊cursor無效，重新掃描即可。

只有nextCursor為null才完成本輪，保存syncUntil作**下一輪包含邊界的updatedSince**；依卡片id／revision去重，避免同秒只用大於時間造成遺漏。處理／保存本批成功後才前進cursor；timeout重送原cursor不會修改資料。途中live更新可能使卡片移動／重複，這不是跨多個HTTP交易的固定DB snapshot，也不承諾長交易延遲提交／倒填時間／刪除的無損changefeed；關閉仍是state，刪除／封存事件另按後續同步契約開放。

## 同步語意：全量、增量、刪除與來源識別

- **全量**：不帶updatedSince呼叫issue-changes並讀到nextCursor為null，即取得目前可見的全部卡片。這包含沒有更新時間的歷史卡。
- **增量**：每輪讀完後，把syncUntil存成下一輪的updatedSince（包含邊界）。
  - syncUntil會比本輪讀取時間早一個緩衝（預設10秒），用來補讀「時間戳已寫入但較晚提交」的寫入。
  - 下一輪會重複出現緩衝期內的卡，請依id與revision去重：revision沒有變大就略過。
- **來源識別**：每個項目附`externalRefs`（TT-171的source／externalId）。外部系統用它對應自己的紀錄，不需要解析標題或描述。沒有綁定時是空陣列。
- **刪除與封存**：2026-11-13（OpenAPI 0.10.0）起Web與公開API可永久刪除卡片（`DELETE …/issues/{issue}`，需`issue.delete`），沒有封存。刪除的卡片不會出現在issue-changes；請另讀`GET /api/public/v1/projects/{project}/issue-deletions`（遞增游標，回issueId／identifier／deletedAt）移除本地資料，保存最後的after供下輪續讀。closed仍只是狀態，照常出現在增量裡。
- **離開範圍**：撤銷Project權限時，整個Project回404，請停止同步並視情況封存本地資料。
  - 帶type／state等篩選時，卡片改成不符合條件後就不會再出現。要做鏡像同步，請不要帶篩選，或定期跑一次全量。
  - 全量對帳：讀完全部，把本輪沒出現的本地紀錄視為已離開範圍。
- **回音辨識**：外部系統自己寫回的變更也會出現在增量裡。每個項目附`lastWrite`：
  - 只有卡片目前的revision是由公開API寫入產生時才有值，並標出sameToken／sameUser。
  - sameToken為true代表這是你自己的寫入，可以略過，避免同步來回循環。
  - 之後若有人在Web修改，revision變大，lastWrite就變成null。
  - 不揭露他人的Token或參照。

## 來源參照與稽核

公開寫入（建立、PATCH、留言、upsert、批次）可以帶選填標頭`X-Client-Reference`，放外部的run、工作或交付物參照，格式為英數與`._:/@#-`，最多120字，格式不符400。

- 參照只寫入來源稽核，不進卡片內容，也不屬於requestId簽章。不要放密碼、Token或其他秘密。
- 本人可在Session的`GET /api/v1/personal-token-audits`看到每筆成功寫入的operation、resourceId、resourceRevision與clientReference。
- 補送：同requestId重送只回原結果，不新增稽核，所以補送保持同一個冪等身分。
- 批次每個成功項目各記一筆稽核；失敗項目保存在該批次receipt的逐項結果，用原requestId重送即可取回。


## 私有附件（OpenAPI 0.13.0）

公開前綴`/api/public/v1/projects/{project}`新增attachments限制、multipart上傳、單筆metadata／內容、單卡`issues/{issue}/attachments`固定每頁20筆與明確bind。路徑、欄位及成功／錯誤Schema以OpenAPI為準

read允許目前有issue.read資格者取metadata／內容；write仍須issue.write才能上傳與綁定。limits需要read及issue.write資格。pending只有上傳者可用, 跨工作區／Project、不再可見的卡片、到期、刪除及撤權均拒絕。內容是原始PNG／JPEG／WebP串流, 有private no-store／nosniff／sandbox與安全下載檔名, 不提供公開key或redirect儲存URL

預設10MiB、2500萬pixels、每卡20張、每人20張未完成、工作區1GiB, 實際部署可調整, 以limits回應為準。上傳每IP30次／60秒, 同時計入公開v1共用每IP120次／60秒。pending預設24小時失效, 刪除保留30天後清理的內部生命週期維持；本版未開放公開刪除附件API

上傳以requestId＋相同檔名／bytes／MIME去重；相同ID換檔案回409, IO失敗回502且可補送相同檔案。保留配額與完成分開鎖後驗權, 外部IO不持有資料庫交易；成功完成receipt與來源只記一次。補送前仍須當下資格, 同一Token若已撤銷不會取回舊結果。明確bind帶expectedRevision與issueRevision, 409不產生半綁定

上傳回傳的reference可放入Issue description, 由既有建卡／改卡交易自動綁定。獨立HTTP驗收使用匿名下載的Python範例及合成圖片, 不依賴Session內部路由作公開功能


## 任務搜尋（OpenAPI 0.14.0）

search是完整卡號時（例如tt-12）, 會轉成大寫並只找同專案該卡, 不再掃描述內提到該卡號的所有卡片。明確尾端星號如TT-12*搜尋卡號前綴。需要找描述／標題提及卡號時用text:TT-12；其他搜尋仍是大小寫不敏感的字面contains, 中文單字、空格、標點及%_不當成SQL萬用字元。identifier參數仍是明確等值條件, 與其他filters交集

沒有把一般文字改成近似的FULLTEXT。列表與增量共用搜尋語意, cursor的filters不可中途更換；卡號prefix可能匹配很多張, 仍須按分頁／cursor接續

## TT-279 列表深分頁

任務列表的(page-1)×perPage最多10000, 超過回400 INVALID_INPUT且不執行Issue查詢。total與lastPage保留真實總數, maxOffset固定10000, accessibleLastPage是可翻閱上限；網頁按此上限停用下一頁並提示縮小篩選。一般PageQuery其他資源及看板分頁不套此任務列表專屬界限

完整第三方同步使用既有issue-changes cursor, 不靠遞增page匯出全部。原有穩定identifier／public_id次排序與changes keyset索引保留, CASE優先級與NULL到期日語意不變。新增倒序索引不代表其他排序或COUNT已變成固定成本

## TT-317 單卡活動總數與工時

Issue詳細新增activityCounts.comments／history／time, 為SQL總數, 不附活動內容；列表不增加活動欄位。每個活動仍按需讀每頁最多100筆（畫面20筆）, 頁籤數字採總數並用分頁total校正

2026-11-13 Josh確認：有issue.read的人可看該卡片所有成員的有效工時與筆數, Epic包含所屬卡片, 作廢紀錄不計。修改仍限本人且要timeEntry.writeOwn；月曆／工作區分析沿原權限, 不因能讀卡片就開放全部報表。跨Project／Workspace或撤權仍拒絕

## TT-302 單卡建卡者與最後異動（OpenAPI 0.18.0）

Issue詳細多了 `createdBy`（建卡者的使用者ID）、`createdByName` 和 `lastChange`（最後一筆異動紀錄的 `actor`、`at`, 沒有紀錄時是null）。TT-314以前建立、對不到人的舊卡, `createdBy` 和 `createdByName` 是空字串。只帶最後操作者和時間, 改了什麼仍然要讀history分頁；history分頁一直是新的在前

## 一般私有附件（OpenAPI 0.18.0）

PDF／ZIP與圖片共用既有附件路由及授權, 但一般附件只能下載, 不接受Markdown圖片引用；下載一律attachment、nosniff與no-store。limits.general提供獨立大小／配額與download-only-structural政策, 原mimeTypes仍只列圖片。格式／ZIP目錄檢查不代表防毒掃描, 詳見consumer的docs/general-attachments.md


## 紙條留言與轉發（OpenAPI 0.19.0）

- `GET /api/public/v1/todos/{todo}/comments`、`/handoffs` 按需讀留言及完整交接紀錄, 使用page／perPage, 上限100, 依createdAt與內部ID穩定正序
- `POST /api/public/v1/todos/{todo}/comments` 帶content（1～2000字）、expectedRevision、requestId新增留言
- `POST /api/public/v1/todos/{todo}/forward` 帶assigneeId、expectedRevision、requestId與可選reply（最多2000字）, 轉給同空間有效成員
- complete／return可附reply, 留言與狀態／交接同交易保存；回覆空白也可完成或轉發
- 建立人、目前承辦人與曾經手者可讀／留言；同空間Owner若未經手也不能讀, 移除工作區資格立即失效
- 只有目前承辦人能完成、轉發或退回, 轉發另需todo.assign；退回沿目前交接路徑回上一手, 不抹除完整歷史。上一手停用時回400, 改選有效對象轉發
- summary不帶留言／交接正文, 新增initialAssigneeName及comment／forward能力。read Token的所有寫入能力為false, write Token仍受當次permission限制
- 相同requestId與payload重送回首次結果, 不重複留言或交接；重播仍先驗有效工作區、參與資格與permission。409時保留草稿, 讀最新revision後由操作者確認再送
