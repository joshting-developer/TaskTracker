# Backend Architecture（TaskTracker適用版）

本文件補充較完整的 Laravel use case 分層。

它不取代既有後端規範, 也不代表每個小功能都要加入 Service、Repository 或 Transformer

命名、docblock、測試與交付規則見 `coding-style-and-tests.md`。

## 適用範圍

適合在下面情況使用：

- 新增或整理包含多個步驟的 API use case
- Controller、Service 與 Repository 的責任開始混在一起
- API response 需要獨立 Transformer
- 同一批資料需要逐筆權限、transaction 或部分成功處理
- 程式雖然可以運作, 但主要流程很難從 Controller 看懂

不需要因為這份 skill 額外改動既有穩定程式, 單純 CRUD 或一兩個明確查詢也不一定需要完整分層

## 開始前先確認

先讀現有 Controller、Service、Repository、Model、測試與相近功能, 再決定這次需要哪些層

要先回答：

- Controller 應該讓人看到哪些 use case 步驟
- 哪些判斷是真正的 business rule
- 哪些資料只能由 Repository 取得或異動
- response 是否複雜到需要 Transformer
- 入口permission、Project scope與逐筆資料條件分別在哪裡驗證
- 批次失敗時要整批 rollback, 還是允許部分成功

不要先建立抽象層再找用途

## 建議依賴方向

```text
Controller
  -> Service
      -> Repository
          -> Model
  -> Transformer
```

- Controller 透過 dependency injection 使用 Service 與 Transformer
- Service 透過 dependency injection 使用 Repository
- Repository 可以注入對應 Model, 讓資料入口固定並方便替換測試相依
- Transformer 使用 Service 已準備好的資料, 不查 DB 也不依賴 Repository
- Controller 不直接使用 Repository 或 Model
- Repository 不決定權限、transaction 流程或 API response 格式

只有實際需要的層才建立, 不為了符合圖形增加空的 wrapper

## Controller 要看得出流程

Controller 應該像 use case 的目錄, 讓人可以直接看出請求會經過哪些階段

Controller 可以負責：

- request validation
- 從 session 或 request 取得操作者資訊
- 依序呼叫名稱清楚的 Service function
- 根據 Service 回傳的業務結果決定下一個流程
- 呼叫 Transformer 組合 response
- 統一處理 HTTP exception、resource 與 request-level log

Controller 不應該負責：

- 拼 Eloquent query
- 判斷群組或資料權限的細節
- 決定 transaction 內的異動順序
- 自己處理去重、資料一致性或 rollback
- 手動組合大量 response 欄位

不要把 Controller 瘦到只剩一個看不出內容的大型 Service 呼叫, 也不要為了顯示流程把 business rule 搬回 Controller

如果 Controller 仍然很長, 先確認是不是 Service 回傳內容不清楚, 或 Controller 正在自己做資料篩選與規則判斷

## Service 負責 business rule

- public function 表示 Controller 需要執行的業務步驟
- private function 拆開權限條件、資料分類、單筆處理與其他內部細節
- Service 決定 Repository 呼叫順序、transaction 範圍與異常處理方式
- Service 不直接讀取 HTTP session, 需要的角色與帳號由 Controller 明確傳入
- 複雜條件抽成有語意的 function, 不讓呼叫端理解底層欄位才能使用

一個 function 同時做權限分類、完整資料查詢、空資料補齊、排序、response mapping 與 log 時, 通常代表責任太多

function 名稱只描述它真的負責的事情, 不要加入只有呼叫端才知道的前提

例如 function 只是取得資料與群組關聯時, 使用 `getItemsWithGroupData()` 會比 `getTransferableItems()` 更準確, 可否轉移應該由真正負責權限分類的 function 決定

## Repository 只負責資料存取

- function 名稱描述查詢或異動行為
- 不處理 HTTP、session、response 或 UI 狀態
- 不決定 business flow 或跨 Repository transaction 順序
- 權限初步分類只查必要欄位, 確認可以處理後再載入完整關聯
- 能批次查詢時不要在迴圈內逐筆查詢
- 同批資料會重複使用的群組或關聯資料要先集中取得

Repository 注入 Model 是固定資料來源與提高可測性的方式, 不是要求每個 Repository 都加入額外邏輯

## Transformer 只轉換 response

Transformer 適合在 response 有多層關聯、固定空值結構、顯示名稱轉換或多種結果狀態時使用

- 只接收已準備好的 domain data
- 不查詢 DB
- 不依賴 Repository
- 不判斷操作者實際擁有哪些權限
- 不執行 transaction 或資料異動
- 同一類 response 的 key 與型別要固定

如果只回傳兩三個原始欄位, 直接在 Controller 使用既有 Resource 可能更簡單, 不需要額外建立 Transformer

## 資料結構與命名

- 不使用 `$context`、`$data2`、`$permissionItems` 這類看不出內容的名稱
- 避免 `compact()`, 直接列出回傳 key
- 回傳多組資料時使用具名 key, 不讓呼叫端依賴陣列位置
- Laravel 查詢與分類過程可以維持 Collection, 到既有 contract 明確需要 array 時再轉換
- 要檢查 key 是否存在時, 可以先 `keyBy()` 或 `flip()`, 再用 `has()`
- 不在同一段流程反覆轉換 Collection 與 array

```php
return [
    'allowedItemIds' => $allowedItemIds,
    'deniedItemIds' => $deniedItemIds,
];
```

具名 array 已經能清楚表達而且只在單一流程使用時, 先維持具名 array

只有資料結構穩定、跨多個 use case 重複使用, 或型別錯誤已經成為實際問題時, 再評估 DTO 或 value object

## API 邊界與前端資料

前端送出的顯示欄位、權限狀態與完整 row 都不能直接作為後端判斷依據

- Controller validation 後建立執行所需欄位的 allowlist
- Service 依可信任 ID 重新取得目前資料與權限
- 操作者帳號與角色優先由後端 session 或 authentication context 取得
- API request／response 與 PHP 變數使用 camelCase, DB 欄位保留既有 snake_case
- GET 與 POST 之間可能發生資料或權限變動, POST 必須重新驗證

## 權限分層

入口permission與逐筆資料權限是兩件事，角色只用於查權限映射

- middleware 或共用 Service function 先驗證有效permission與scope能不能進入功能
- Service 再逐筆判斷操作者能不能處理指定資料
- 通過入口permission不代表可以操作 request 內的所有資料
- 前端的 disabled 狀態只能改善 UX, 不能取代後端驗證
- 重要異動可在 transaction 內鎖定資料後再驗證一次, 避免檢查與寫入之間的狀態變動

未登入或無權限回傳 401 或 403 要跟既有產品 contract 對齊, 不在單一功能內自行改變全域語意

## 批次 API

遇到逐筆權限、部分成功、單筆 rollback、固定空資料結構或同人轉移等需求時, 再閱讀 [批次 API 設計與 review](batch-api-review.md)

## 測試分工

- Service Unit test 驗證 business rule、權限分類、單筆流程與 exception 分支
- Repository Unit test 驗證查詢條件、批次欄位與回傳型別
- Transformer Unit test 驗證完整、空資料與失敗結果的固定 response 結構
- Controller test 驗證 request validation、session 資料、Service 呼叫順序與 response
- Authorization Feature test 驗證 middleware、permission ID及Workspace／Project scope
- DB Feature test 驗證 transaction、跨資料表一致性、rollback、去重與部分成功

Mock 回傳值必須依 production method 的真實 contract 建立, 不要在測試內另外發明一套資料結構

## 避免擴大範圍

- 不因為看到相近舊功能就順便重構
- 不為了表面共用把不同 domain 綁進同一個 Service
- 共通規則可以一致, 實際資料異動流程仍可分開
- 沒有第二個明確使用情境前, 不先建立 interface、base class 或通用 framework
- 只整理這次改動直接造成的 unused code, 既有問題另外記錄

## 完成前 review

確認：

- Controller 不用往下追 query 就能看懂主要流程
- Service function 名稱和實際責任一致
- Repository 沒有 business rule 或 response mapping
- Transformer 沒有資料查詢或權限判斷
- 前端欄位沒有被當成可信任權限或資料來源
- GET 與 POST 都在正確時機重新驗證權限
- transaction 邊界符合整批或逐筆成功需求
- response key、null 與空陣列 contract 已固定並有測試
- 批次查詢沒有明顯 N+1
- 測試環境無法執行時, 未驗證範圍有明確說明
