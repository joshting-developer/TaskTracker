# TT-401 GPT Plugin：經明確確認刪除卡片

沿用90分鐘估時, 只實作delete_issue工具及合成／隔離刪除驗收, 沒有授權刪任何正式卡。先讀完整目標卡號、標題與revision供確認, 簽章綁定目標及payload；有子卡／工時、無issue.delete及409由正式API拒絕。平台標示destructiveHint=true, 不自動重試；同requestId由後端重播結果, 不重新產生
