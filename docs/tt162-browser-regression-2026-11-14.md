# TT-162 瀏覽器回歸紀錄

本次共執行59支檢查, 以Codex隔離前端5199與MySQL後端13199驗證, 不操作現用資料

原始結果保留在artifacts/tt162/browser-suite, 5支重跑結果在browser-rerun；inline寄信另用inline＋log mailer驗證, 沒寄真信

結果：34支通過（含5支修正設定或fixture後重跑）, 17支環境受阻, 8支仍失敗, 不代表完整回歸通過

## 已修正並重跑

- account-settings、attachment-view、issue-history-order：補漏掉的信箱驗證或修正第二租戶context, 沒有放寬產品驗證
- board-query：側邊欄改為連結後, 測試改用link角色
- guest-pages：設定VITE_SITE_ORIGIN後驗證分享metadata, 產品沒有改動

## 未通過與交接

- SMTP狀態文字12px：既有TT-334, 不調低可讀性門檻
- browser-check:320、issue-time-check:160：舊斷言要求reader看不到工時tab, 與目前允許讀取卡片工時的產品契約不同；需改驗可讀、不可填改
- api-budget-check:70：詳細頁新增attachments/limits不在舊allowlist；要先核對必要請求與上限, 不直接加寬預算
- lookup-options-check:78：把看板摘要當成含selectedIssueIds的詳細回應, 應另讀看板詳情
- refresh-scope-check:46：仍比對舊報表API, 需同步目前time-report介面
- issue-form-check:65、issue-inline-check:76：等待「已加入描述」逾時, 尚未確認是附件行為退化或測試時序／文案不同, 不先宣稱產品Bug
- 17支環境受阻：舊專用API埠與Compose名稱、缺隔離正式runtime、MinIO／seed／雙註冊政策環境、來源不符, 或Node fetch使用相對URL；保留安全guard, 交由測試工具後續卡處理

## 各支最終狀態

| 檢查 | 結果 |
| --- | --- |
| account-settings-check.js | 重跑通過 |
| api-budget-check.js | 失敗 |
| attachment-api-check.js | 通過 |
| attachment-draft-check.js | 通過 |
| attachment-view-check.js | 重跑通過 |
| board-archive-check.js | 通過 |
| board-description-check.js | 通過 |
| board-list-summary-check.js | 通過 |
| board-mount-check.js | 通過 |
| board-performance-check.js | 環境受阻 |
| board-query-check.js | 重跑通過 |
| board-ux-check.js | 通過 |
| browser-check.js | 失敗 |
| bundle-check.js | 通過 |
| comment-attachments-check.js | 環境受阻 |
| developer-docs-check.js | 環境受阻 |
| email-verification-check.js | 環境受阻 |
| general-attachments-check.js | 環境受阻 |
| guest-pages-check.js | 重跑通過 |
| home-check.js | 通過 |
| inline-mail-check.js | 通過 |
| issue-activity-browser-check.js | 環境受阻 |
| issue-delete-check.js | 通過 |
| issue-form-check.js | 失敗 |
| issue-history-order-check.js | 重跑通過 |
| issue-inline-check.js | 失敗 |
| issue-pagination-check.js | 環境受阻 |
| issue-reading-check.js | 通過 |
| issue-row-actions-check.js | 通過 |
| issue-search-check.js | 通過 |
| issue-sort-check.js | 通過 |
| issue-time-check.js | 失敗 |
| lookup-options-check.js | 失敗 |
| markdown-check.js | 通過 |
| member-time-details-check.js | 通過 |
| personal-query-check.js | 通過 |
| personal-tokens-check.js | 環境受阻 |
| poll-drafts-check.js | 通過 |
| production-smoke-check.js | 環境受阻 |
| project-acl-query-check.js | 環境受阻 |
| project-utility-check.js | 通過 |
| r2-attachment-check.js | 環境受阻 |
| realtime-check.js | 環境受阻 |
| refresh-scope-check.js | 失敗 |
| registration-policy-check.js | 環境受阻 |
| sidebar-links-check.js | 通過 |
| state-filter-crash-check.js | 通過 |
| status-notification-check.js | 通過 |
| time-analysis-check.js | 通過 |
| time-analysis-seed-check.js | 環境受阻 |
| time-calendar-check.js | 通過 |
| todo-collaboration-check.js | 環境受阻 |
| todo-date-check.js | 通過 |
| todo-quick-layout-check.js | 環境受阻 |
| uiux-check.js | 失敗 |
| usability-fixes-check.js | 通過 |
| workspace-payload-check.js | 通過 |
| workspace-settings-query-check.js | 環境受阻 |
| workspace-switch-check.js | 通過 |

## 本卡其他驗證

同步寄信與維護CLI後端完整403項測試、17093項斷言通過（1項既有skip）, 最後讀取調整後另跑57項525斷言及15項148斷言專項；冒煙通過, 69張正式視覺比較通過

TT-162仍缺Fly App建立後的外部定時觸發、喚醒、失敗通知與費用驗證, 程式提交不等於雲端部署完成
