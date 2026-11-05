# Epic定位與競品對照

研究日期：2026-11-06（Asia/Taipei）。本輪是使用者提問後的研究與建議，沒有授權改畫面或流程；下列建議未採納前不實作。

## 結論

Epic是常見的工作分組／較大交付範圍，不是所有專案管理都必須採用的流程中心。競品支援Epic或父子階層，不代表必須常駐獨立的Epic概覽。TaskTracker目前將專頁、進度、工時、未歸類提示及結案檢查集中在Epic上，確實提高其產品權重；這是我們目前的實作選擇，不應說成業界必然。

## 官方文件事實

| 產品 | 文件明確支援的方式 | 對本專案的啟示（助理推論） |
| --- | --- | --- |
| YouTrack | 看板泳道可按自訂欄位、專案或特定類型Issue分組；使用Issue作泳道時，加入卡片會建立子任務連結 | Epic可作分組維度，不必讓日常操作先經過Epic專頁 |
| OpenProject | Work package有父子階層，在詳細頁Relations與表格顯示；表格階層可開關 | 階層能力可整合到既有清單與詳細頁，而非另造一個主要工作入口 |
| Jira | 有Epic規劃面板，可在board／backlog檢視設定開關；可把工作從Epic移到No epic範圍 | 即使產品重視Epic，仍可將其視為按需使用的規劃能力，允許無Epic工作 |

來源：[YouTrack看板設定](https://www.jetbrains.com/help/youtrack/cloud/configure-agile-board.html)、[OpenProject關聯與階層](https://www.openproject.org/docs/user-guide/work-packages/work-package-relations-hierarchies/)、[Jira檢視設定](https://support.atlassian.com/jira-software-cloud/docs/customize-your-view-of-the-board-and-backlog/)、[Jira管理Epic](https://support.atlassian.com/jira-software-cloud/docs/manage-epics-in-a-scrum-project/)。版本與模板會影響實際畫面，不宣稱所有競品介面相同，也未將文件調研當使用者研究。

## 目前實作與問題

- 側欄有獨立Epic概覽；同頁顯示完成比例、預估／實際工時、負責人及子卡清單。
- Task／Bug可不掛Epic，但「未掛入Epic」常駐提示可能讓人以為待辦資料不完整。這是可用性推論，尚無真人研究。
- Epic改為完成會檢查所屬卡片；這是目前產品規則，競品具備父子關聯不能直接證明這種阻擋是通用標準。
- 畫面20%只是有效子卡的完成件數比例（取消排除），不是依估時／工作量加權，不應理解成實際交付已做完20%。
- 2026-11-06已允許Epic直接填實際工時；工時統計與估時限制另有現行契約，本次討論不自動撤回。

## 助理建議，待使用者採納

1. 日常入口維持任務清單與看板。Epic保留為可選的大工作／父項，不要求每張Task／Bug歸類。
2. 考慮移除或降級獨立Epic概覽，將查找Epic放進清單類型篩選、看板分組及卡片關聯。若列表要支援Epic需另改現有執行卡篩選，不能只隱藏入口讓Epic難以找到。
3. 不常駐提醒未掛Epic；這不屬於錯誤。完成率與工時彙總留在該Epic詳細頁，後續分析頁再依使用情境設計。
4. 是否保留子卡未完禁止Epic結案，另行確認；不能因本次質疑重要性就自行移除業務規則。

例如「會員功能第一版」可有Epic，下面掛登入、註冊、忘記密碼；一張小Bug可獨立追蹤，不必為了歸類另外開Epic。是否使用看工作範圍與協作需要，不能只由團隊大小決定。
