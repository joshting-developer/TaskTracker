# 重播原結果版本化無損編碼（TT-255，2026-11-12）

接續[TT-227容量分析](performance-receipt-retention-2026-11-12.md)。本卡只改原結果的**儲存編碼**：不加TTL、不刪receipt、不改API輸出、不改重播語意。

## 格式

`operation_receipts.result`／`todo_operation_receipts.result`仍是JSON欄位，不改schema。大結果改存envelope：

```json
{ "__receiptCodec": "gzip-v1", "sha256": "<原JSON的sha256>", "bytes": 61858, "data": "<gzip level 6的base64>" }
```

- 只有JSON≥`min_bytes`（預設4096 B）**且**envelope比原JSON小兩成以上才編碼；小結果（例如66 B的Todo）與壓縮不了的內容維持純JSON。
- 讀取（`ReceiptCodec::decode`）永遠同時接受純JSON與`gzip-v1`。

## 重播契約（不變）

1. 呼叫端先完成當次授權（permission／scope／作者條件）。
2. 以Workspace＋userId:requestId找receipt，先比對operation＋輸入簽章，不同即409 `REQUEST_CONFLICT`。
3. 簽章相同才解碼，回原結果，不讀最新Model。
4. 不認識的codec、base64損壞、gzip損壞、長度或sha256不符：回500 `RECEIPT_UNREADABLE`，**保留key、不重新執行**，不會被當成找不到receipt。

## 啟用與回退

| 設定 | 預設 | 說明 |
|---|---|---|
| `TASKTRACKER_RECEIPT_CODEC` | `json` | 新receipt的寫入格式；`gzip-v1`啟用編碼 |
| `TASKTRACKER_RECEIPT_CODEC_MIN_BYTES` | `4096` | 編碼門檻 |

建議順序：

1. 部署含雙讀的版本（本卡）。此時仍寫純JSON，舊版與新版worker都能讀全部receipt。
2. 確認所有web／queue worker都已是新版本，再設定`TASKTRACKER_RECEIPT_CODEC=gzip-v1`。
3. 視需要轉換既有資料：`php artisan tasktracker:receipt-codec encode --batch=200`（可先加`--dry-run`）。

回退到不認識envelope的舊版本：先把設定改回`json`，再執行`php artisan tasktracker:receipt-codec decode`，最後才部署舊版。

批次指令依id游標分批，每批一個交易並`lockForUpdate`；寫入前再解碼比對，確認無損才更新。損壞資料保留原樣、計入`unreadable`並以exit code 1結束，不刪除任何receipt。重跑冪等，因為gzip header的mtime固定，同內容會得到同樣的envelope。

## 量測（隔離MySQL 8.4，`ReceiptCodecMySqlTest`）

與TT-227相同的工作量：同一張卡64輪改標題＋留言，共129筆receipt。

| 指標 | 純JSON | gzip-v1 |
|---|---:|---:|
| JSON_STORAGE_SIZE合計 | 4,270,754 B | 535,780 B（-87.5%） |
| 實際編碼筆數 | — | 123／129（6筆小於門檻） |
| 129筆全部encode／decode CPU | — | 56 ms／40 ms |
| 同key重播程序內中位數（15次） | 11.6 ms | 12.2 ms |

- 重播延遲是Laravel測試kernel的程序內量測，不含網路。差距約0.5 ms，在單次量測波動範圍內，不能當作正式HTTP p95。
- 儲存數字是binary JSON payload，不是InnoDB頁面、binlog或備份大小。
- 壓縮不改變「每次寫入保存完整卡片結果」的成長型態，根本解法仍是TT-178／246／169的精簡寫入回應。

## 決策

收益成立：大型receipt省87.5%，CPU與重播延遲的代價很小。但預設仍是`json`，原因是一旦寫入envelope，回退到舊版前必須先執行`decode`。要不要在本機環境開啟`gzip-v1`並轉換既有資料，由使用者決定。

## 驗證

- `ReceiptCodecTest`（memory，3 tests）：
  - 門檻與壓縮收益不足時原樣保存；中文／emoji／巢狀／null／false無損往返，經JSON欄位保存後仍無損。
  - 未啟用寫入時仍可讀envelope；五種損壞與未知codec一律`RECEIPT_UNREADABLE`。
  - HTTP：同key回原結果、舊純JSON receipt雙讀、改payload為409、小Todo不編碼；receipt損壞時不重新執行並保留key。
  - 批次：dry-run不寫入、encode後重播不變、重跑冪等、decode還原為原本的純JSON、損壞資料保留並回報。
- `ReceiptCodecMySqlTest`與既有`ReceiptGrowthTest`在隔離MySQL通過（5 tests／550 assertions）；完整memory suite 96 passed。
