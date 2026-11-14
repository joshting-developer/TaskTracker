# TT-335 步驟

- [x] frontend/tools寫死的5175改讀 `E2E_FRONTEND_PORT`（35支）, 文件測試埠白名單加5198／5199
- [x] Python契約檢查白名單加13198／13199；測試Compose允許5198／5199來源, 帳號限流倍數50
- [x] 帳號相關限流乘 `auth_throttle_scale`, 只有testing生效；新增AuthThrottleScaleTest
- [x] 新增 `npm run test:smoke`
- [x] 新增專案專用Skill `.agents/skills/tasktracker-test-environments`, AGENTS與skills/README加入口

## 驗證結果（Claude隔離環境 tasktracker-claude-test, 前端5198／後端13198）

- AuthThrottleScaleTest通過（正式／local／staging就算設倍數也是1, testing才放寬, 預設路由次數不變）；Pint通過
- 所有改過的工具 `node --check` 語法通過
- 隔離環境讀回：TASKTRACKER_AUTH_THROTTLE_SCALE=50, 註冊路由限流1000/分鐘, 來源包含5198／5199
- `npm run test:smoke` 在5198通過, 13秒
- 同一個隔離環境連續跑13支會註冊帳號的檢查, 沒有任何一次429；通過：home、usability-fixes、workspace-switch、status-notification、state-filter-crash、time-analysis、member-time-details、time-calendar、project-utility
- 沒過但跟這張無關：uiux-check（通知預覽SMTP卡字級12, 要求13）、account-settings-check（第二個帳號找不到「重新整理工作空間」）、browser-check（兩次分別停在936行與1188行, 每次不同, 像是不穩定）
- 後端SQLite全套243通過, 5支失敗：4支是Codex進行中TT-318未提交的SymfonySecurityRegressionTest, 1支是之前就失敗的R2ConfigurationTest
- 沒有實測Claude和Codex真的同時跑；兩邊的埠、隔離後端和Compose名稱都不同, 理論上不會撞, 要等Codex也照Skill跑過才算驗到

## 後續修正（2026-11-14, Codex回報）

- 問題：隔離Compose給瀏覽器測試設 `TASKTRACKER_AUTH_THROTTLE_SCALE=50`, 在同一個容器跑PHPUnit時, AuthThrottleScaleTest::test_default_routes_keep_original_limits第68行預期1實際50
- 原因：phpunit.xml的 `<env force>` 只蓋 `$_ENV` 和putenv, Laravel先讀 `$_SERVER`, 容器帶進來的50還在
- 修正：phpunit.xml同時用 `<env force>` 和 `<server force>` 把倍數固定成1；只影響PHPUnit, 瀏覽器測試用的隔離後端還是50
- [x] 修改前用倍數50重現失敗；修改後倍數50／1／沒設三種都通過
- [x] HEAD＋這次修改的後端SQLite全套, 帶倍數50連跑4次：3次413支全過（152支只在MySQL跑而跳過）, 1次在PublicAttachmentsTest的finfo->file中途中斷, 單獨跑該class與後續兩次全套都過, 看起來是偶發, 跟這次改動無關
