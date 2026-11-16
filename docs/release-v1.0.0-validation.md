# v1.0.0 整批驗收與部署

TT-396, 2026-11-16。Season 0 共17張卡已完成, 正式站已部署 v1.0.0

- 正式入口：https://task-tracker.josh-ting.com
- 版本 tag：`v1.0.0`, 原始碼 `15b50c540d0f82956c1f00e2f2a4bcfd5a8ac9a5`
- [發布 CI 43815701574](https://github.com/joshting-developer/TaskTracker/actions/runs/43815701574)：validate、source、contract、image、deploy 全部成功
- Fly 映像：`registry.fly.io/tasktracker-josh:v1.0.0`
- Digest：`sha256:7eea754effcca31c0046adedaf168edefc1b96a767fbfaa01248ce923eb6c862`
- 維持單台 nrt／512MB, 未新增付費資源

## 範圍

這批包含計畫日期、紙條時間、專案標籤與公開 API、登入導覽、註冊確認密碼及工作區顯示時區。TT-395 本地捕捉信箱已獨立驗收, 不修改正式郵件設定。GPT 私人 Plugin 由另一流程交付, 不混入主站映像；交付與限制見 [GPT Plugin 紀錄](gpt-plugin-delivery-2026-11-16.md)

Season 1／2 與 Future 沒有開工, 本次只依清冊補標

## 資料與 migration

正式 MySQL 已先取一致備份, 私有 SQL 為 5,650,285 bytes, SHA-256 `699761ea11abe9cfcc46c80c43bfc24b00d3b404b55623d8a49fd85e30d25589`。備份在無外部網路、無公開埠的獨立 MySQL 還原成功, 當時核對47表、4帳號、1工作區、550卡片；這些是備份時的筆數, 不代表後續追蹤寫入後的即時筆數

四個加法 migration 在副本預演後, 已使用專用 migration 帳號及 TLS CA 驗證套用到正式庫：

- `2026_11_15_050000_add_issue_planned_dates`
- `2026_11_15_060000_create_project_tags`
- `2026_11_15_070000_index_issue_tag_relations`
- `2026_11_16_000000_add_workspace_display_timezone`

正式端重新核對無待執行 migration。沒有 seed、reset 或匯入覆寫；還原容器已清理, 私有備份保留

## 驗證結果

- 完整後端524項／20,249 assertions 通過, 1項 R2 專用測試另組驗證
- 一般前端75項通過, 包含兩項記錄原因後的精準重跑；policy／inline／pagination 與新版 seed／realtime／R2／runtime 專用流程通過, 後四組退出碼皆0且完成清理
- 發版標準 smoke 通過, 包含 routes／API／composables／build／真實瀏覽器
- Chrome157 同環境162張核准視覺比較通過, threshold 0.02／maxDiffPixels 0 不變；spacing／overflow／hide-action 三種退化均被拒絕, 原樣與還原通過
- actionlint、Skill 完整性與證據檢查通過；秘密掃描438個提交未發現洩漏
- 正式瀏覽器使用 Codex 本人帳號, 驗證 Session、Secure／HttpOnly Cookie、一般帳號無管理後台資格、有界設定／標籤／通知查詢、卡片標籤與獨立計畫日期、工作區顯示時區及 UTC ISO 時間
- 正式標籤管理1440／768／390三尺寸無橫向溢出, 公開 OpenAPI 為0.24.0。沒有寫入合成業務資料

正式補標已逐卡寫後讀回：Season 0 共17張、Season 1 共10張、Season 2 共11張、「暫不執行」共9張。不因補標開始後續工作, Tag 也不取代權限

早期失敗紀錄保留, 包括測試容器停止、固定時鐘跨日造成 Cookie／CSRF 失效及舊 Chrome 版本不符。最終以正常時鐘及核准 Chrome157 完成驗證, 沒有放寬比較參數

## 證據與限制

忽略的 `artifacts/tt396/` 保留 `deployed-image.json`、`formal-migrations.json`、`release-migration-rehearsal.json`、`formal-season-tags.json`、`production/result.json` 與發布檢查日誌；視覺比較與退化證據在 `artifacts/tt398/`。資料備份、憑證與截圖不進 Git

瀏覽器三尺寸不等於實體手機驗收。舊通知若曾存錯時間, 沒有可靠原始時間前不批量平移；工時工作日仍採原 Asia/Taipei 契約

保留前版 v0.5.0 digest `sha256:c26c78b09d14881b13cc6f8b332793c249f4e94f9ec0b279c9d6dd43353262ed`。本次 schema 為加法, 已預演相容性；沒有實際回退正式映像, 也沒有用舊備份覆蓋新寫入
