# TT-331 瀏覽器回歸

全量60支檢查保留原始紀錄, 再逐支重跑受本卡影響的入口。最終36支通過、15支環境受阻、8支仍失敗、1支inline專用模式未重跑, 不宣稱全量通過。另有舊todo-checks入口委派三支新流程, 已實際執行通過

本卡直接流程已通過：紙條、首頁、Markdown、PAT CLI協作、25筆分頁與通知、44px排版、帳號設定、bundle、日期、工作區切換及可用性。重跑log分別在artifacts/tt331/rerun、rerun-final、personal-query.log、layout.log與legacy-entry.log

8支既有失敗仍由TT-336／TT-334追蹤：API預算attachments/limits白名單、reader工時tab舊斷言、看板摘要當詳細、報表舊網址、兩個附件插入提示逾時、SMTP字級12px。沒有刪掉檢查、放寬可讀性或增加請求預算。15支環境受阻是既有專用埠／MinIO／正式runtime／雙註冊政策／seed或相對URL設定, 不改用現用服務執行

| 檢查 | 最後結果 |
| --- | --- |
| account-settings-check.js | passed-rerun |
| api-budget-check.js | failed |
| attachment-api-check.js | passed |
| attachment-draft-check.js | passed |
| attachment-view-check.js | passed |
| board-archive-check.js | passed |
| board-description-check.js | passed |
| board-list-summary-check.js | passed |
| board-mount-check.js | passed |
| board-performance-check.js | environment-blocked |
| board-query-check.js | passed |
| board-ux-check.js | passed |
| browser-check.js | failed |
| bundle-check.js | passed-rerun |
| comment-attachments-check.js | environment-blocked |
| developer-docs-check.js | environment-blocked |
| email-verification-check.js | environment-blocked |
| general-attachments-check.js | environment-blocked |
| guest-pages-check.js | passed |
| home-check.js | passed |
| inline-mail-check.js | not-rerun-inline-mode-required |
| issue-activity-browser-check.js | environment-blocked |
| issue-delete-check.js | passed |
| issue-form-check.js | failed |
| issue-history-order-check.js | passed |
| issue-inline-check.js | failed |
| issue-pagination-check.js | environment-blocked |
| issue-reading-check.js | passed |
| issue-row-actions-check.js | passed |
| issue-search-check.js | passed |
| issue-sort-check.js | passed |
| issue-time-check.js | failed |
| lookup-options-check.js | failed |
| markdown-check.js | passed-rerun |
| member-time-details-check.js | passed |
| notes-page-check.js | passed-rerun |
| personal-query-check.js | passed-rerun |
| personal-tokens-check.js | environment-blocked |
| poll-drafts-check.js | passed |
| production-smoke-check.js | environment-blocked |
| project-acl-query-check.js | environment-blocked |
| project-utility-check.js | passed |
| r2-attachment-check.js | environment-blocked |
| realtime-check.js | environment-blocked |
| refresh-scope-check.js | failed |
| registration-policy-check.js | environment-blocked |
| sidebar-links-check.js | passed |
| state-filter-crash-check.js | passed |
| status-notification-check.js | passed |
| time-analysis-check.js | passed |
| time-analysis-seed-check.js | environment-blocked |
| time-calendar-check.js | passed |
| todo-collaboration-check.js | passed-rerun |
| todo-date-check.js | passed |
| todo-quick-layout-check.js | passed-rerun |
| uiux-check.js | failed |
| usability-fixes-check.js | passed |
| workspace-payload-check.js | passed |
| workspace-settings-query-check.js | environment-blocked |
| workspace-switch-check.js | passed-rerun |
