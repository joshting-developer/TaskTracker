# 執行紀錄

- 已查重 TT-193 closed 與 TT-408 review, 新增本卡承辦 Bridge Tag 缺漏, 不重開核心功能卡
- 正式 0.24.0 新增六個 Tag operation, 舊 Bridge 未映射；ISSUE_FIELDS 也未保留 tagIds
- Sites 原生讀回目標 joshting.1999 唯一 owner, 不改帳號、Secret 或存取對象
- 新增 list_tags／get_tag／create_tag／update_tag／delete_tag／update_issue_tags, 對應正式六個 Tag 操作；刪除預覽明示保留歷史與退役定義的效果
- 卡片詳細、列表與增量保留 tags, 並由正式 tags 衍生 tagIds；貼標預覽也顯示現有標籤。只接受 addTagIds／removeTagIds, 不增加整包取代入口
- 初輪測試抓到 MCP discovery 仍寫死 24 個唯讀工具, 改為本版正確 26；最終 83 項全部通過, build 與正式 OpenAPI0.24.0 全部 47 個 operation 對照通過
- 用 Codex 本人 PAT 唯讀實測新 Adapter：取得 4 個正式標籤定義, TT-193 revision16 回傳 tags／tagIds。沒有正式建標／貼標／刪標, 沒有使用或輸出 GPT Secret
- 共用 Skill 完整性通過, 本次為 Adapter 契約與投影修正, 無產品畫面或核心 Laravel 改動；不重跑核心 API 與無關全站瀏覽器回歸
- 已提出既有私人 Bridge 的部署授權問題, 等明確答覆才部署；本輪未宣稱 Host 已載入新工具
