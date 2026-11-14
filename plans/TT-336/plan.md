# TT-336 同步瀏覽器回歸工具與現有API、工時權限及隔離環境契約

## 目標
把frontend/tools底下跟目前產品契約對不上的瀏覽器檢查同步回來, 能在Codex 5199／13199和Claude 5198／13198兩套隔離環境跑；不刪斷言、不放寬預算或可讀性門檻

## 範圍（以TT-338最新重跑為準, artifacts/tt338/browser-classified.json）
12支失敗或逾時：account-settings、api-budget、browser-check、guest-pages、issue-form、issue-inline、issue-time、lookup-options、markdown、refresh-scope、uiux、workspace-switch
15支環境前置不足：逐支列出缺什麼, 能用隔離環境補的就補, 需要R2／正式runtime／Realtime等特殊環境的分開記

## 不做
- uiux的SMTP 12px歸TT-334
- 不動Codex TT-339進行中的檔案（notes-page-check.js、issue-references-check.js、Todo元件、MarkdownContent.vue等）
- 附件提示逾時先重現根因, 是產品退化就另開Bug, 不改斷言蒙混

## 驗證
每支修完在5198／13198重跑, 最後跑冒煙和完整瀏覽器回歸, 逐支列通過或缺什麼
