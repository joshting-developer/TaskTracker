# Coding Style 與測試細節

這份規範由CLM共用文件搬入TaskTracker；專案適用邊界見上層SKILL.md。

適用情境：

- Laravel / PHP 後端開發
- CLM 相關模組開發
- migration、測試、API、log、設定檔異動
- 需要對齊團隊既有開發風格與交付標準時

不適用情境：

- issue 專屬工作順序
- 單一專案或單一 issue 的暫時性限制
- 與後端 coding style 無關的 UI / 設計規範

## 快速原則

- 一次聚焦一個清楚範圍
- 先定義驗證方式，再開始改動
- 優先跑受影響模組的最小測試子集
- 例外處理、測試命名、function 註解都要一致
- 設定檔、migration、log 異動都要同步考慮安裝包、升級包與相依流程

## 開發前 Checklist

### 需求與驗證

- 確認這次改動有明確驗證方式
- 確認 API / DB / 顯示語意沒有混在一起
- 確認輸入 / 輸出格式若有改動，已記錄格式、限制與相容策略

### API 規格文件

- API 行為有改動時，確認是否需要同步更新 API 規格文件
- 若欄位格式、限制條件、相容策略改變，應明確記錄

## 開發中 Checklist

### Laravel

- Controller 應能方便區分不同 exception
- 若在 service 丟 exception，優先維持一致格式，例如：

```php
throw new \App\Domain\DomainError('REVISION_CONFLICT', '資料已更新，請重新載入後重試');
```

- 避免直接使用 `shell_exec()`、`exec()` 等指令，降低 cmd injection 風險
- 若需要執行底層指令，優先使用 `Process` 套件

### 可讀性與條件判斷

- 不要使用需要讀者心算的布林比較，例如 `($isActive) === $shouldBeActive`、`(A === true) === B`
- 複雜條件要拆成有語意的變數，例如 `$isActive`、`$shouldStopService`、`$shouldRestartService`
- 條件分支應直接表達業務意圖，不要用反向布林、雙重否定或技巧式寫法壓短行數
- 若一個 `if` 條件需要額外解釋才看得懂，優先抽成 private method，並用 method 名稱說明意圖
- 重構既有難讀邏輯時，先用測試鎖住原行為，再改成語意清楚的寫法
- 交付前回看新增或改動的條件式：半年後維護者是否能不用推理就看懂

### 共用時間工具 / Carbon macro

- 若專案中已存在共用時間規則、resolver、Carbon macro 或 converter，優先使用既有入口，不要在 service / helper 內重新發明一套時間轉換
- 若需要處理 `SystemTime / ClmTime`、timezone convert、固定格式時間解析，先搜尋專案是否已有 macro，例如 `nowSystemTime`、`nowClmTime`、`parseSystemTime`、`parseClmTime`、`parseSystemTimeByFormat`、`parseClmTimeByFormat`、`toSystemTime`、`toClmTime`
- 若只是依照目前系統設定做時間轉換，應優先走共用 macro，而不是自己直接寫 `Carbon::parse(...)->setTimezone(...)`
- 若這次修改會擴充時間規則，應先確認現有 macro / resolver 是否能延伸，避免新舊規則並存
- 測試若要驗證最終時間結果，expected value 應優先使用固定值或舊算法，不要直接用同一套新 macro / helper 自己驗自己

### Migration

- 新增 `not null` 欄位時，migration 內要處理預設值
- 欄位調整成唯一值時，要同步通知文件 / 升級流程做檢查與備註

### Log 與相依功能

- log 格式或內容有異動時，要確認相關功能與 Kibana dashboard 是否受影響
- 若功能本身依賴 log，應同步留下維護與檢查資訊

### 命名規則

- 變數名稱：小駝峰
- function 名稱：小駝峰
- 資料夾名稱：大駝峰
- 檔案名稱 / class 名稱：大駝峰

### Function 註解

- 每個 function 都要有中文註解
- 有參數的function須逐一提供`@param`，無參數時不虛構參數
- 每個 function 都要有 `@return`

範例：

```php
/**
 * 取得使用者時區設定
 *
 * @param int $userId 使用者 ID
 * @return array
 */
public function getTimezoneSetting(int $userId)
{
    // ...
}
```

### Class 註解

- 每個 class 都要有中文 docblock，放在 `class` 宣告前
- Class 註解應說明這個 class 的主要責任，不要只重複 class 名稱
- 若 class 有重要相依、外部副作用、排程/API/DB/log 行為，應在 class 註解中簡短標明

範例：

```php
/**
 * 檢查 ES 磁碟狀態並管理 Logstash / Beats 服務
 */
class CheckLogstash extends Command
{
    // ...
}
```

### Report / PDF

- 若修改 Kibana dashboard，需確認 Report getPDF 產出的 PDF 排版沒有問題

### 安裝包 / 升級包

- 設定檔異動時，確認安裝包 / 升級包是否需要同步調整
- 設定檔異動時，確認 `grep` 或其他安裝流程檢查沒有漏改

## 開發後 Checklist

### 測試

- 測試命名規則：`action_Result_Test_case_name`
- `Action` 應對應 function 名稱
- `Result` 使用 `Success` / `Failure`
- data provider 命名規則：`providerAction_Result_Test_case`
- 後端功能調整時要同時評估 Unit test 與 Feature test 是否都需要補齊
- Unit test 優先覆蓋單一 class / service 的分支、exception、資料轉換、mock 相依互動
- Feature test 優先覆蓋跨 service、Controller/API、middleware/session、DB 實際寫入、model 關聯與外鍵、transaction rollback 等整合行為
- 若功能會查詢或寫入資料庫，例如匯入、建立、更新、刪除、外部設定名稱轉 id，除了 Unit test 驗證流程外，也應補 Feature test 建立 testing DB 假資料並確認實際資料表結果
- Feature test 必須確認執行於 `testing` 環境，並優先使用 `DatabaseTransactions` 或既有測試資料清理方式避免污染資料
- 優先執行受影響模組的最小測試子集
- 區分「這次改動造成的問題」與「既有測試噪音」

範例：

```php
/**
 * 匯入失敗，當 token 不存在時應回傳錯誤
 *
 * @return void
 */
#[\PHPUnit\Framework\Attributes\Test]
#[\PHPUnit\Framework\Attributes\DataProvider('providerImport_Failure_No_token_exists')]
public function import_Failure_No_token_exists()
{
    (...前置動作...)

    $res = 被測試的function();

    ...assert / expected...
}

/**
 * 提供匯入失敗且 token 不存在的測試資料
 *
 * @return array
 */
public static function providerImport_Failure_No_token_exists()
{
}
```


### 測試維護與 fixture

- 修測試前先跑最小範圍取得真實錯誤，不只依賴舊文件、舊註解或 CI 摘要。
- 在 namespaced PHP 檔案新增 class、facade、static method 或 class constant 的短名稱引用時，逐一確認已有正確的 `use` import；若不匯入，就必須使用完整 FQCN。不要假設 PHP 會從其他測試檔或 production code 沿用 import。
- 新增或修改上述 class reference 後，提交前至少執行直接涵蓋該行的 test method，再執行完整測試檔。`git diff --check`、`php -l` 與人工閱讀都不能取代 runtime class resolution 驗證。
- 修改測試檔後，收尾前必須重跑該完整測試檔；只跑單一 filter 不足以代表該檔案已回歸。
- 若本機 PHP、extension 或 dependency 異常，優先改用專案既有 container、測試 VM 或 targeted CI 執行；若仍無法重跑完整受影響測試檔，必須在回覆與 MR 說明中明確列出未驗證範圍與風險，不得把 lint 或 diff check 描述為測試通過。
- 測試修正優先調整 test fixture、mock、expected value；不要把 production code 或 schema 調整混進同一個測試修復，除非已確認 production code 本身就是問題來源。
- Unit / Feature test 應主動建立自己需要的資料，不依賴本機、CI 或既有 testing DB 中剛好存在的 seed。
- 不要用 skipped 掩蓋可由 fixture 解決的資料不足問題；只有真的依賴不可控外部服務或資料條件時，才考慮 skip。
- 單一 test 專用資料可以放在 test method 附近；多個 test 都需要的預設資料才放進 `setUp()`、`tearDown()` 或 helper。
- 如果要改 fixture 管理風格，應考慮整個 test class 或分類一致性，不要只讓單一測試群採用特別寫法。
- schema 有新舊差異時，fixture 可用 `Schema::hasColumn()` 做相容，但不要因此修改 production schema。
- 外部副作用可以隔離，但 collaborator contract 不能消失；若 mock 掉外部 API、檔案寫入、logging 或 static collaborator，應用 expectation 確認必要呼叫有發生。
- 若測試使用 mock / fake 取代 service collaborator，mock 回傳值必須回查 production method 的實際 contract；不要憑印象手寫 translation key、error code、HTTP status、array structure 或 exception message。
- 測試 assertion 應驗 production contract，不要讓 mock 自行發明一組值後又拿這組值當 expected。
- 純 DB 行為若是 feature test 的主體，優先保留真實執行，不要全部 mock 掉。
- `Log::spy()` 可用來隔離實際 logging 副作用；若測試目標不是 logging，通常不用驗證 log file。若要測 logging，應明確使用 `Log::shouldHaveReceived()` 或針對 `CLMLog` 做 expectation。
- Laravel `ValidationException` 優先 catch 後驗 `$e->errors()` 的欄位；business exception 才直接驗固定 message。
- 不要用 `exceptionMessage => null` 表達 validation failure，也不要為了支援模糊 data provider 放入大量 optional assertion；應拆清楚 validation 與 business exception。
- 不要硬比對 Laravel / PHP 預設 exception wording，例如 `Undefined index` 與 `Undefined array key`；必要時只驗穩定關鍵字或更明確的錯誤欄位。
- 不為了消除 risky 而加入無語意的 `assertTrue(true)`；若測試語意是「執行成功且不丟 exception」，可以保留 `expectNotToPerformAssertions()`。
- 如果單檔通過但整個資料夾失敗，要追查 Mockery alias、static/global state、class 已載入、shared fixture 或測試順序相依。
- rebase、合併其他分支或套用他人版本後，先前測試結果不可直接沿用；至少重跑本次負責的最小測試檔與 CI 報告中對應的失敗檔案。
- 如果測試涉及檔案 fixture，避免依賴前序測試建立或刪除檔案；每個 case 應自行準備需要的檔案狀態。
- 測試疑似打到錯誤 DB 時，先檢查 Laravel config cache；`php artisan config:cache` 可能讓測試吃到 cached `.env` 而不是 `.env.testing`，必要時先執行 `php artisan config:clear`。

### 交付前確認

- API 改動後，再確認一次輸入 / 輸出格式
- DB schema 改動後，再確認一次 migration 風險
- log 異動後，再確認一次 Kibana 與相依功能
- 設定檔異動後，再確認一次安裝包 / 升級包
- 留下足夠註解、測試與驗證紀錄，讓後續維護者可以接手

## 工作方式建議

- 小步修改
- 每一步都有驗證方式
- 先修清楚，再擴大範圍
- 若測試失敗，先分辨是新回歸還是既有噪音
- 若牽涉 API、DB、log、安裝包、升級包，優先把相依面一起盤清楚
