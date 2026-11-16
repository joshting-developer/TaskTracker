# TT-351 GPT Plugin：建立、部分更新卡片與留言

既有估時120分鐘。沿用createPublicIssue／updatePublicIssue／createPublicIssueComment, 專案與候選依TT-399查詢。寫入分預覽及確認執行, 簽章綁定內容、操作、requestId、使用者／工作區及有效期, 修改內容須重新確認。每次重驗Token與有效上游權限, 409不覆蓋, 不自動重試。只合成人造資料測試, 不用正式卡片驗破壞或重播

只修改Adapter與本卡plans, 不包含其他對話TT-398差異。工程／資料範圍／Plugin及Sites skill沿前卡已讀規範, 沒有產品UI或Laravel查詢變更
