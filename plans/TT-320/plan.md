# TT-320 tag發布與自動部署

- Josh退回首次部署紙條, 希望打tag直接部署, 並要求先檢查推GitHub的內容風險；沿用預估180分鐘
- 先查目前GitHub權限、既有CI與tracked／history秘密風險, 不輸出秘密, 不直接push／tag
- 版本流程需明確驗證、發布固定映像與部署, prod Secrets只進deploy job, 一般PR／push不發布
- 優先沿用現有runtime、契約CI及Fly設定；準備可檢閱成果與必要測試, 未驗證／缺權限據實回報

## 批次發布授權（2026-11-14）

Josh指定TT-159 → TT-344 → TT-320 → TT-344結果複查 → TT-346 → TT-353／354, 每卡仍commit／回填, 最後整批只打一個tag部署。不逐卡發版, main push只驗CI；TT-320可先交付流程待最後tag驗收

當前AGENTS新增持續push授權, push前fetch／必要時pull --rebase同步, 有衝突即停。最終tag只在完整驗證與資料備份可用後推送
