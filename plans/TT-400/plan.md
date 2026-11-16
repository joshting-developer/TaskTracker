# TT-400 GPT Plugin：建立、修改看板與差量收錄卡片

沿用90分鐘估時, 補create_board／update_board兩工具。使用正式JSON schema及相同預覽／確認流程；PATCH看板先讀最新revision, 只送名稱／簡述或addIssueIds／removeIssueIds, 不覆寫整份收錄。Default不接受寫入, 封存板與board.manage由核心API限制。先合成回歸與隔離MySQL HTTP, 再同批私人發布
