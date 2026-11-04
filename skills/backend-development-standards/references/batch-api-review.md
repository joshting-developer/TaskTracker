# 批次 API 設計與 review

只有功能真的包含多筆資料、逐筆權限、部分成功或獨立 transaction 時才需要讀這份 reference

## 先確認批次語意

開始實作前先定義：

- request 是否允許重複 ID
- response 順序依 request、ID 或其他欄位
- 找不到資料要視為 request error, 還是單筆失敗
- 權限不足的項目要出現在查詢 response、執行 response 或只寫 log
- 單筆失敗是否繼續後面的資料
- 使用者要求沒有實質異動的操作時, 要略過、回成功或回失敗
- response 是否需要固定 key 與型別

這些是產品 contract, 不要因為 GET 或 POST 寫起來方便就自行決定

## 建議處理流程

```text
validate request shape
  -> validate endpoint permission and workspace/project scope
  -> normalize and reject duplicate IDs when required
  -> batch load minimal permission fields
  -> partition allowed and denied items
  -> load full relations only for allowed items
  -> process allowed items
  -> transform results
  -> sort by the documented rule
  -> log denied and failed IDs with reasons
```

權限分類 function 回傳具名結果：

```php
return [
    'allowedItemIds' => $allowedItemIds,
    'deniedItemIds' => $deniedItemIds,
];
```

不要使用 `[0]`、`[1]` 或含義模糊的 context

## 查詢 API 與執行 API 可以不同

只有契約明確允許公開該筆存在性時，查詢API才可保留無權限row供前端顯示disabled；TaskTracker未授權Project及其Issue不得回傳ID、標題或存在性

執行 API 可能只回傳真正有處理的項目, 把無權限 ID 留在 log

也可能因產品需求採用其他做法, 重點是兩邊要分開定義並寫進測試, 不要假設一定對稱

若回傳無權限 row, response 結構仍保持一致：

```json
{
  "itemId": 101,
  "displayName": null,
  "owner": null,
  "contacts": [],
  "editable": false,
  "reason": null
}
```

有意義的識別欄位照常回傳, 物件使用 `null`, 集合使用空陣列, 前端不用先判斷 key 是否存在

## 逐筆 transaction

只有需求允許部分成功時才使用逐筆 transaction

- batch loop 放在 transaction 外
- 每一筆異動在自己的 transaction 完成
- 同一筆相關資料異動要放在同一個 transaction
- 寫入前視需求使用 `lockForUpdate()` 重新取得主資料
- transaction 內重新驗證資料存在、逐筆權限與接收資料合法性
- catch 單筆 exception 後記錄結果並繼續下一筆
- log 至少包含可識別 ID 與底層原因

若需求是整批原子性, 就不應套用逐筆 transaction

## Idempotent 與 no-op

要明確定義來源與接收者相同、接收資料已存在或使用者重送 request 時的結果

常見原則：

- 相同來源與接收者仍走正常 validation 與處理入口
- 沒有實質異動時不建立重複資料
- 已存在的 relation 不重複 insert
- no-op 是否回成功由產品 contract 決定
- application-level 查重若沒有 DB unique constraint, 要明確記錄併發風險

不要只在前端阻擋, 後端仍要保證資料一致性

## 查詢效能

- 權限分類先批次查必要 ID 與群組 key
- 將允許集合 `flip()` 或 `keyBy()` 後用 `has()` 查找
- 完整 relation 只載入允許處理的資料
- 相同群組、使用者或設定資料只查一次並建立 map
- 測試要驗證 Repository 呼叫次數, 避免後續改動重新產生 N+1

## 測試矩陣

至少確認：

- 全部成功
- 可處理與無權限資料混合
- 第一筆失敗後第二筆仍執行
- 單筆 transaction rollback 不影響其他筆
- request 內重複 ID
- GET 後到 POST 前資料或權限改變
- 接收者不存在或不屬於允許範圍
- 來源與接收者相同
- relation 原本已存在時不重複新增
- response 排序、固定 key、`null` 與空陣列
- 失敗 log 包含資料 ID 與原因

驗證部分成功時, 不只 assert response, 也要查實際 DB 狀態
