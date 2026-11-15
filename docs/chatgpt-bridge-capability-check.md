# TT-348 私人ChatGPT整合能力檢查

2026-11-15, 沿用docs/chatgpt-tasktracker-plugin-plan-2026-11-14.md的私人Sites Adapter方案

## 結論

平台文件與目前帳號的私人Site、Secret設定門檻可支援下一步合成工具開發, 沒有發現要改做公開匿名代理的理由。這不是已發布Plugin或已連上TaskTracker的聲明

## 文件依據

[OpenAI Sites Plugin說明](https://help.openai.com/en/articles/20001547-hosting-a-plugin-with-chatgpt-sites)說明Site可託管MCP, 由owner發布後產生Plugin, 安裝和外部服務授權是後續步驟。文件也明確要求先以假資料測試

Sites Skill的site-mcp-server reference規範由Sites先驗呼叫者的Site存取權, 再提供Site範圍身分標頭；identity-and-secrets reference規範使用原生工具配置伺服器機密。這些是平台契約, 不等於本Adapter已通過執行時驗證

[OpenAI外部MCP認證說明](https://developers.openai.com/plugins/build/auth)要求獨立受保護MCP採OAuth 2.1。不能把TaskTracker PAT直接當作ChatGPT外部MCP認證；目前採Sites託管身份與私人的伺服器端API轉接, 不另造Laravel業務層

## 當前帳號實際結果

| 檢查 | 結果 |
| --- | --- |
| 既有Site發現 | 列表空, 沒有可沿用的Site |
| 建立私人Site | TaskTracker Bridge, project_id保存在integrations/chatgpt-bridge/.openai/hosting.json |
| 存取政策 | owner, custom access, 僅owner一人；沒有群組、編輯者或外部訪客 |
| 發布狀態 | 版本0, current_live_url為null, 尚未發布 |
| Secret設定 | 合成TT_CAPABILITY_PROBE以is_secret=true成功寫入 |
| Secret讀回 | 原生讀取回is_secret=true、value=null, 沒有合成值明文 |
| 探針清理 | 移除成功, 設定revision升到2 |
| MCP連線發現 | 未發布時回Publish this Site before connecting, 不當成已安裝或已獲MCP執行權限 |
| 正式PAT | 沒有建立、傳入或沿用既有Agent PAT |

不記錄原生工具回傳的短效來源憑證或服務存取Token, 不把秘密放進Git、工具輸出或卡片

## 下一階段驗證

TT-349先實作8種唯讀工具, 固定TaskTracker正式origin、拒絕redirect與任意URL、列表有界、錯誤與惡意正文處理, 只用合成資料。私人發布後才實測MCP協定、owner與匿名存取邊界、執行時Secret不進工具輸出／前端／log, 及固定HTTPS目的地可連線

上述執行時項目目前未測, 若私人MCP或出站限制實測不成立就停止正式PAT接入, 不把平台環境變數配置成功當成安全驗收通過。正式資料唯讀聯調與安裝由後續卡接續
