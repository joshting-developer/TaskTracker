# 自有後端PHPDoc盤點與統一（TT-235，2026-11-12）

依[PHPDoc規範](../skills/tasktracker-development-standards/references/phpdoc.md)與[重構規格](backend-di-phpdoc-refactor-plan.md)第4、6節。

## 工具

- `backend/tests/Support/PhpDocInventory.php`：
  - 以PHP tokenizer逐檔讀取，不執行原始碼、不bootstrap Laravel。
  - 檢查：具名Class／Trait／Interface／Enum有docblock、方法有docblock與摘要句、每個簽章參數都有`@param`（名稱帶`$`、型別存在）、`@param`沒有簽章以外的名稱、非建構子方法有`@return`。
  - 匿名class不要求docblock；attribute在docblock與宣告之間時仍可辨識。
- `backend/tools/audit-phpdoc.php`：輸出摘要JSON，`--list`列出每項問題。用法：`php tools/audit-phpdoc.php [--list] [--all]`。
- `backend/tests/Unit/PhpDocCoverageTest.php`：回歸門檻，app／seeders／tests/Support／tests/Concerns必須零缺漏，並驗證掃描器本身的判斷。tools與factories不是每個Compose都掛載，所以由CLI完整掃描，避免讀到映像內的舊檔。

## 範圍與結果

必須齊備的範圍：`app/`、`database/seeders/`、`database/factories/`、`tools/`、`tests/Support/`、`tests/Concerns/`、`tests/TestCase.php`、`tests/MySqlTestCase.php`。

| 必須範圍 | 之前 | 之後 |
|---|---:|---:|
| 檔案／類別／方法 | 213／206／691 | 214／206／691 |
| 有問題的檔案 | 55 | **0** |
| 缺參數說明 | 37 | 0 |
| 缺摘要句 | 32 | 0 |
| 缺`@return` | 30 | 0 |
| 方法無docblock | 17 | 0 |
| 類別無docblock | 6 | 0 |
| `@param`與簽章不符 | 0 | 0 |

修正方式：

- 參數型別一律取自實際簽章，說明逐項寫實際用途，沒有用通用字串批次替換。
- 單行docblock整理成「摘要／空行／標籤」的多行格式，保留原有說明文字。
- 未改任何程式邏輯、例外語意或權限判斷。
- 測試支援類別中的完整類別名稱改用`use`匯入，以符合Pint。

## 參考範圍（未列入本次必須）

`tests/Feature`、`tests/Unit`、`database/migrations`、`routes`、`config`、`bootstrap`：87檔中32檔共114項，主要是73個Feature／Unit測試方法沒有docblock，以及migration的`up`／`down`。依規格這些只需檢視既有註解是否矛盾，本次沒有逐一補齊；以`--all`可列出清單，可另開卡處理。

## 已知與本卡無關的格式問題

`app/Console/Commands/CheckInfrastructure.php`、`app/Services/IssueReadService.php`、`tests/Feature/ProjectBoardsTest.php`在HEAD就有Pint格式問題（例如壓縮成一行的方法），本卡只動docblock，沒有順帶重排。

## 驗證

- `audit-phpdoc.php`：必須範圍0問題。
- `PhpDocCoverageTest`：2 passed。
- 主容器memory完整suite：133 passed；1筆`TokenSpecificationTest`失敗，來自進行中且未提交的openapi.json少了`x-required-token-ability`，在本卡修改前就失敗。
- 隔離MySQL：非公開API測試全部通過；21筆公開API／文件測試因隔離容器未掛載`backend/resources`而失敗，與本卡無關。


## 2026-11-12 TT-261 後續完成

上方參考範圍未補與格式問題是TT-235當時紀錄。TT-261重新盤點目前100檔／241方法，已補齊33檔116項，reference與required均0問題；Feature／Unit／migration與框架設定來源已納入門檻。原生MySQL65項與memory155項通過，沒有修改斷言或migration邏輯。完整範圍、AST比對與限制見[TT-261交付](backend-phpdoc-reference-2026-11-12.md)。
