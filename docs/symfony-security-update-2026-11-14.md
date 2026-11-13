# TT-318 Symfony安全修補

## 範圍

只更新4個已被公告影響的Symfony元件, Laravel維持13.37.0, Docker維持PHP8.5.12。未新增audit ignore、關閉安全檢查或更新其他套件

| 套件 | 原版本 | 修補後 |
| --- | --- | --- |
| symfony/http-foundation | 8.1.9 | 8.1.11 |
| symfony/http-kernel | 8.1.9 | 8.1.11 |
| symfony/mime | 8.1.7 | 8.1.10 |
| symfony/routing | 8.1.9 | 8.1.11 |

Composer原本宣告PHP ^8.3, 但既有Symfony8.1系列已要求>=8.4.1, 這次將專案宣告改為^8.4.1, 不再讓使用者以為目前lock能裝在PHP8.3。這是對齊既有依賴下限, 不是把Docker runtime再升版

## 公告與TaskTracker適用條件

以下是依目前程式與官方公告做的盤點, 不等於7個漏洞都已在TaskTracker產品端重現。沒有用「目前沒走到」當理由保留有公告的版本

| 公告 | 主要條件與目前判斷 |
| --- | --- |
| [HEAD方法匹配](https://symfony.com/cve-2026-118514) | GET方法matcher漏比HEAD。TaskTracker走Laravel權限邊界, 未使用Symfony SecurityBundle的方法規則；新增依賴行為回歸, GET matcher必須攔到HEAD |
| [腳本名稱前綴](https://github.com/symfony/symfony/security/advisories/GHSA-3h27-hjv7-fqjx) | 前置代理對路徑的判斷可能與PHP切出的路徑不同。正式Nginx仍需遵守既有路由與授權, 不假定前置規則能取代後端資格重驗 |
| [private_ranges代理信任](https://github.com/symfony/symfony/security/advisories/GHSA-xg2q-4rrq-657q) | 需使用private_ranges／PRIVATE_SUBNETS作代理允許清單。目前bootstrap沒有這種設定；日後Fly代理配置仍須指定可信代理, 不能照抄舊常數 |
| [生成URL的主機污染](https://github.com/symfony/symfony/security/advisories/GHSA-5mx8-mwrp-5693) | 特定前置腳本重寫配合惡意路徑影響生成URL。驗證連結目前以config(app.url)建立, 沒有拿來訪者Host生成；新增Request回歸確認惡意路徑不成為base URL |
| [inline fragment轉送標頭](https://github.com/symfony/symfony/security/advisories/GHSA-wg5q-m2ww-f3c9) | 需Symfony inline fragment子請求與loopback代理條件。TaskTracker是JSON API, 未使用Twig／FragmentHandler／該RouterListener流程 |
| [MIME參數名稱注入](https://symfony.com/cve-2026-45070) | 不可信參數名稱含CRLF可注入郵件標頭。目前業務使用固定標頭名稱, 附件檔名經HeaderUtils處理；新增正常filename與惡意參數名稱回歸, 不將檔名當參數名稱 |
| [host placeholder吞路徑](https://symfony.com/cve-2026-118516) | 需含host placeholder的編譯路由與依路徑的存取控制。目前routes沒有domain placeholder；仍更新routing, 避免未來新增路由帶回風險 |

## 驗證與限制

證據集中在忽略的artifacts/tt318：更新前後audit JSON、實際vendor的平台檢查、新映像建置、隔離MySQL／memory及瀏覽器回歸。四個HTTP／MIME行為在舊vendor全部失敗, 新vendor需全部通過；MIME確認的是RfcComplianceException, 不靠任意exception當成功

更新lock與建立測試映像不代表Fly已發布, 也不代表依賴以外的安全問題都已驗收。OCI防火牆依Josh決定保留, TT-327另追；主庫過大權限TT-330及Fly端到端TT-159仍未完成。本次不寄真信、不改現用DB資料

## 本次實測結果

- 新依賴隔離MySQL＋memory全套389項、16981斷言通過, 1項既有skip；包含公開API／Scope／租戶隔離／附件／路由及GrowthBudget回歸
- 新增4項安全行為7個斷言通過, 修前4項失敗；audit-after的advisories與abandoned皆空, check-platform-reqs及Composer validate通過
- PHP8.5.12開發映像與PHP-FPM／Nginx正式映像均建置成功, 正式映像未發布或接現用資料庫
- 依專案測試環境Skill以5199／13199執行test:smoke通過；真HTTP附件位元偵測、10MiB限制、私有串流、重試與跨租戶拒絕通過。fixture曾誤用未定義client, 已改直接解析upload response, 不影響產品
- 此次未跑frontend/tools全部瀏覽器腳本, 既有非相關失敗依TT-319交接／TT-334追蹤；不將本次範圍報成全產品回歸通過
- 最終66張三尺寸正式比較通過, approved與environmentMatches皆true；留言按需載入的擷取競態已改等實際狀態, 原失敗產物保留, 像素門檻未調高
