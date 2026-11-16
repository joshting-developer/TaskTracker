# TT-350 私人發布與連線驗收

2026-11-16恢復：使用者提供新PAT並授權設定。原生Sites確認joshting.1999為唯一owner；先更新Secret及發布, 再修正Bridge未承認write包含read的判斷。僅變更Adapter授權與合成回歸, 讀取範圍、GET端點、有界分頁及owner隔離維持。共享工作樹已有TT-398異動, 本次只提交TT-350相關檔案, 不改其卡片或內容

沿用既有估時, TT-349 1f0799d已交付32項合成／契約測試。原階段沿用舊Site。2026-11-15 Josh明確要求換正確帳號重建, 已由新帳號新建appgprj_6af99efb261c8191941e0e30391c219f, 舊站保留, 不改公開

1. 核對owner-only政策與正式OpenAPI, 以原repository的Adapter子樹保存Sites來源, 不push整個TaskTracker或任何秘密
2. 打包已驗證Worker, 私人發布並核對MCP Plugin工具、匿名拒絕與owner身分
3. 使用者透過安全設定建立專用短效唯讀PAT後再連線, 不複用Agent PAT／Owner降級, 不在聊天要求原文
4. 真實既有卡只讀、卡號定位與撤權等按授權分層驗證。缺安全連線或Plugin安裝時明確保留受阻, 發紙條並繼續不依賴此項的工作
