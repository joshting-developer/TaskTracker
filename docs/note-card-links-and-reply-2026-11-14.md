# TT-339 紙條卡號連結與回覆上一手

Josh希望紙條提到TT-331或#TT-331時能直接開卡片, 選接收人時也能快速選上一手

## 已採用的方式

- 紙條正文與留言的卡號轉為原生連結, 另開任務詳細頁, 保留原紙條與草稿
- 既有Markdown連結、行內程式碼與程式碼區塊不再加工；原始HTML維持逸出, Vue只為符合卡號格式的純文字產生固定站內路徑
- 使用既有無Project深連結及issue-locations, 點擊後才解析正規公開ID；不逐卡呼叫API, 也不預先抓整個Workspace
- 接收人選單名稱採「回覆上一手」, 選擇後填回覆再送出。伺服器沿目前交接路徑返回, 同步保存回覆及交接紀錄；不是以紙條建立人推測對象
- 沒有交接路徑的自記紙條不出現快捷項目；上一手停用、版本衝突或資格變動時, 沿用既有錯誤與草稿保留流程

## API與資料存取

`GET /api/v1/issue-locations/{issue}`原有UUID方式維持, 增加完整identifier方式, 回應仍只有id、projectId, 不附卡片內容

Controller → IssueReadService → IssueReadRepository。Repository回最小位置, Service重新驗證issue.read；無權、不存在與跨工作區同為404。沒有新增角色名稱放行或略過DI

卡號查詢先限定可信Workspace及Project code, 再用issues.identifier找單筆, 利用既有workspaces.public_id、projects.workspace_id/code及issues.workspace_id/project_id/identifier索引。UUID方式沿既有公開ID定位, first有單筆上限, 不讀歷史、留言或工時, 不新增排序或分頁列表

紙條只在按更多後讀本人有權閱覽的原稿與20筆歷史分頁。連結在文字渲染時不查資料, 新分頁只發一次位置查詢後走原詳細頁流程。回覆上一手重用returnTodo, 保留當下permission、租戶、版本、requestId與重播前授權；前端沒有指定上一手ID或回退其他帳號

這是功能修改, 不是部署效能報告；本地隔離MySQL及瀏覽器驗證不能當遠端p95或RUM。完整回歸中尚未修完的檢查仍留TT-336／TT-334, 不宣稱整體驗收通過

## 驗證記錄

具體結果記在plans/TT-339/step.md, 詳細日誌與合成截圖放忽略的artifacts/tt339。只對隔離資料庫建立測試帳號與紙條, 不用業務庫seed或發真信
