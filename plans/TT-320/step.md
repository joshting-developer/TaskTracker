# TT-320 進度

- 已完整讀Josh退回紙條：希望tag直接部署, 先檢查GitHub提交風險；TT-162分析已交付後才claim本卡
- 既有api-contract與visual工作流程沒有tag發布, 所以目前只有打tag不會部署；開始檢查GitHub資格與內容安全

- TT-344本機全契約通過並提交4deb9fa, main push被自動審查兩度拒絕, 即使7a5a22e有持續push規則仍要求當次確認, 已向Josh提出問題, 未繞過
- 已準備tag workflow：main HEAD＋版本校驗→秘密歷史掃描／共用契約CI→linux/amd64 build與合成runtime smoke→private GHCR版本／git標籤→production job轉推Fly同映像
- production token不進build；新增migration未有私有備份方案就拒絕自動部署, guard兩測試通過
- 官方actionlint1.7.12校驗碼驗證與workflow靜態檢查通過；本機候選runtime路由／秘密排除／mysqlnd三秒設定通過, 不當作CI amd64已驗
- Gitleaks掃346commit／16.93MB無秘密, 精準忽略既有測試向量, 沒排整個test。GHCR與Fly實際發布尚未執行, 等最後整批唯一tag

- 再核對CLI help：deploy wait-timeout為duration字串, 已改5m而非無單位300, 避免最後發布才遇參數錯誤
