# 自有後端PHPDoc與註解

## 格式與語意

- Class／Trait／Interface有清楚用途；方法使用真正的 `/** ... */`，每行以 `*` 開頭，不用Markdown列表符號代替PHPDoc格式。
- 說明以繁體中文為主，首句具體說明業務用途或重要副作用；不寫只有「取得資料」「設定依賴」或重複函式名稱的空文案。業務識別碼／變數不翻成中文。
- 描述與@param／@return間空一行；每個參數名帶 `$`，型別、nullable、union、預設值與實際簽章一致，不能捏造必填語意。
- 非建構子方法原則上補齊@param與@return；無參數可省@param，void寫@return void，never依真實行為標註。建構子描述有意義的注入依賴及非顯然的情境限制，不為重複型別堆字或捏造回傳。
- 原生型別優先。集合／分頁補泛型，陣列補可信array shape（如array{projectId:string,revision:int}）、callable與nullable／union精確描述；集合如Collection<int, Issue>依實際元素型別標示，不把全部降級成array／mixed，也不能為求精確而臆測型別。
- @throws只寫真正可能向外傳遞的例外；示範中的類別、DomainError或分頁泛型要按實際實作核對，不能原樣抄來造成假契約。
- 保留有效@template、@phpstan-type、@mixin等工具資訊；刪除／修正過時矛盾註解，不批次替換字串後就稱完成。
- 允許為明確授權的全面整理產生大Diff，仍逐檔核對，不暗改業務結果、例外語意、權限或資料模型。一般修改依當次範圍補齊；不因skill生效自行重寫全庫。

## 範例

```php
/**
 * 依目前工作區及專案範圍讀取任務標題，供已授權的摘要查詢使用。
 *
 * @param string $issueId 任務公開識別碼
 * @return string|null 存在時的標題；查無資料時為null
 */
public function title(string $issueId): ?string
{
    // 僅示意註解與簽章的對應；實際查詢及授權依現有用例設計。
    return null;
}
```

## 驗收

對照實際簽章及分支檢查@param名稱／數量、@return、null／void／never、泛型、shape與throws。全庫整理須列應處理檔案／方法、完成數、合理例外與覆蓋率分母；核心檔案已補不等於全庫完成，格式一致不等於型別正確。

用現有Pint、PHP語法／型別工具及相關測試；沒有PHPStan不因整理註解就任意安裝大型依賴。靜態分析／框架限制如需例外，明確記錄，不刪有效工具資訊以消除警告。
