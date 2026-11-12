# 整包Workspace／Todos讀取退役矩陣（TT-180，2026-11-12）

`getWorkspace`回傳整個Workspace的Project、Issue、看板、成員、工時與通知；`getTodos`回傳本人全部待辦與成員。一般頁面改用分責查詢（見[分責查詢契約](query-api-v1.md)）後，這兩個入口只剩下列使用者。

## 產品前端：已退役

| 位置 | 原用途 | 現況 |
|---|---|---|
| `App.vue::refreshWorkspace` | 非限定頁面的整包載入 | 已移除。所有路由都只讀`auth/current`＋目前Project；原分支在`isScopedRoute`涵蓋全部路由後已走不到 |
| `App.vue::readHash`舊`#/issue/<id>` | 從整包Issue找Project | 已移除，改由refresh呼叫`/issue-locations/{issue}`（TT-180前段） |
| 首頁／Project目錄／設定／分析／個人待辦 | 整包預載 | 前段9413b7e已改分頁與按需查詢 |
| 看板／列表／詳細／Project設定／成員／通知預覽／工時月曆 | 整包資料 | TT-240～245、TT-223等專用查詢；看板背景輪次另有TT-148版本檢查 |

`frontend/src/api/index.js`仍保留`getWorkspace`／`getTodos`方法，供下列測試做資料核對；產品程式呼叫由`workspace-payload-check.js`靜態掃描禁止。

## 驗證（Network）

`frontend/tools/workspace-payload-check.js`在隔離MySQL依序走過18個頁面：首頁、專案列表、任務列表、看板列表、Default／自訂看板、任務完整頁、專案設定／成員／通知／工時、工作區設定與成員、工時分析四頁、舊任務連結。之後看板背景輪詢兩輪。結果：`getWorkspace`／`getTodos`為0次，共99個API請求。

## 尚保留的使用者

| 使用者 | 用途 | 處理 |
|---|---|---|
| `frontend/tools/*-check.js`（browser-check、uiux-check、todo-checks、board-ux-check、account-settings-check、workspace-switch-check、performance-scan） | 測試寫入後以整包資料核對結果 | 保留為驗證用途，不屬一般頁面；可逐步改用單筆查詢，但不影響產品流量 |
| `tools/tasktracker.py` `import`／`verify`與`.local`一次性腳本 | 依描述追蹤鍵比對全部卡片 | 內部管理工具。日常`list`／`claim`／`status`／`comment`已用分頁與單筆API；`import`／`verify`需要描述，改分頁需逐筆讀詳細，留待PAT／公開API（TT-165～169）後再遷移 |
| `backend/tests`（約15個Feature測試） | 回歸與舊契約 | 保留 |
| 後端RPC `getWorkspace`／`getTodos` | 上述測試與工具 | 暫不移除。退役條件：測試與工具改用分責查詢後，另卡移除路由與服務，並同步文件 |

## 限制

- App仍保留舊全量資料結構（`workspace.issues`等）與只在非任務路由才會出現的舊詳細／看板模板；資料永遠為空，不會發出請求。清理屬於後續重構，不影響本卡的資料量目標。
- 本卡不處理刷新範圍（TT-219）與效能門檻（TT-226）。
