# 公開API契約CI（TT-253）

2026-11-12。正式來源仍為 `backend/resources/openapi.json`，本輪版本0.4.2；新增Scope機器註記，未新增業務端點。

## 執行

GitHub Actions：`.github/workflows/api-contract.yml`，PR及main push執行，僅contents read，不需要正式環境秘密。本輪未push，遠端job尚未觸發。

本地需Docker、Python venv（安裝`tools/requirements-openapi.txt`）、Node22與已build前端及Playwright Chromium：

```sh
sh tools/api-contract-ci.sh
```

macOS若使用已安裝Chrome，可設定`PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH`指向Chrome executable。CI會自行安裝Chromium；不採用開發機特定路徑。

runner固定隔離Compose專案`tasktracker-contract-ci`、13049、`tasktracker_test`帳號／schema。MySQL資料在tmpfs，秘密僅初始化於獨立volume。開始及結束清理的只有該專案，異常退出同樣清理；現用主庫、Redis、Agent帳號與其他測試專案均不使用。埠衝突直接失敗，不停止佔用者。請勿同時執行兩份同名runner。

## 門檻

- 固定官方OpenAPI3.1 Schema離線驗結構；三種結構破壞測試必須被拒絕。
- 正反兩向比對實作路由／method與規格，限制公開API及明確Session Token管理範圍；operationId唯一、Bearer／Cookie認證、read／write Scope註記、FormRequest必填與request／response範例符合正式Schema。
- 七種故意漂移：安全方案缺失、錯誤Scope、重複operationId、多列／漏列路由、缺expectedRevision、過時身分範例，均須失敗。
- 隔離Laravel HTTP測試實際Bearer認證、授權／租戶隔離、分頁及資料投影、錯誤、revision／requestId、冪等與無Session；另以MySQL驗公開讀寫及撤銷競態。
- 只用人造註冊fixture取得PAT，公開URL下載Python與cURL範例，在暫存目錄執行身分→Project／狀態→建卡→定位→更新／留言／重播；不讀應用原始碼，測後撤銷Token並登出。
- 文件5頁×3尺寸與鍵盤流程；真HTTP 401→429／Retry-After／rate headers與匿名規格不耗配額。限流測試最後執行。

棄用規則仍依公開contract；不可讓新端點在未同步規格時通過，也不可在規格提前承諾未實作能力。指南純敘述的語意仍需review，CI不能證明每一句自然語言完整正確。TT-254負責更完整、只憑公開文件的獨立客戶端拒絕流程驗收，不能由範例happy path取代。

Docker context已加入Laravel resources，確保全新image包含正式規格及公開文件，不依賴人工copy。

## 實測結果

2026-11-12於macOS Docker本地跑完整runner成功：memory 22通過／13原生類略過（2285 assertions）；MySQL14通過（508 assertions）；官方結構與3種結構負向、7種路由／欄位／安全／範例漂移、下載py／sh流程、文件15個頁面尺寸及鍵盤、真HTTP限流皆通過。結束後專用容器／network／secret volumes已移除。記錄不含PAT、密碼或Cookie。GitHub Actions本身未觸發，非正式部署驗收或效能p95量測。
