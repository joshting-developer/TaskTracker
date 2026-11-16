# 執行紀錄

讀卡後claim。官方Sites MCP規範只承諾標準MCP content, 未提供此Site專用檔案picker；使用標準base64輸入及embedded resource輸出, 最終Host實測限制如實保留

77項Adapter測試通過, 含multipart真實File位元組、簽章內容不可更換、下載MCP embedded resource及大小／檔名／URL拒絕。JSON仍16KiB, 附件獨立6MiB上限, 查limits同時回adapterMaxBytes避免誤認等同API上限。依Josh指示不重測已驗附件API；尚未驗Host檔案picker與呈現, 保留待本人驗收, 不把標準MCP輸出測試當成Host成功。正式工時未填
