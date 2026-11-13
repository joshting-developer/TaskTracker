# TT-284 移除App.vue不可達的舊整包任務工作區

- 來源是TT-179評估的決策1：App.vue裡還留著早期用整包資料畫的任務列表、看板、詳細頁和編輯面板, 但已經走不到
- 確認走不到的根據：App的 `workspace.issues` 一律是空陣列（整包讀取早就退役）, 所以舊詳細頁和編輯面板不可能出現；有選專案又在任務頁時, `isTaskRoute` 一定成立, 改由ProjectTasksWorkspace顯示
- 只刪App.vue裡的使用, IssueList、ProjectBoard等元件和composable還被ProjectTasksWorkspace用, 檔案保留
- 不改網址、API或其他頁面行為；不引入Router／Pinia
