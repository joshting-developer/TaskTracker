# TT-402 GPT Plugin：外部識別查詢、upsert與批次匯入

沿用150分鐘估時, 薄轉接3個已驗正式API。來源與externalId依正式pattern, upsert既有卡版本由API重驗；批次上限50項, 保留逐項結果及summary。沿用相同預覽／確認／requestId, 不自動翻頁或重試。依Josh最新指示只補Adapter輸入、輸出、方法與確認驗證, 不重做核心API測試
