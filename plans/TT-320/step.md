# TT-320 進度

- 已完整讀Josh退回紙條：希望tag直接部署, 先檢查GitHub提交風險；TT-162分析已交付後才claim本卡
- 既有api-contract與visual工作流程沒有tag發布, 所以目前只有打tag不會部署；開始檢查GitHub資格與內容安全

- TT-344本機全契約通過並提交4deb9fa, main push被自動審查兩度拒絕, 即使7a5a22e有持續push規則仍要求當次確認, 已向Josh提出問題, 未繞過
- 已準備tag workflow：main HEAD＋版本校驗→秘密歷史掃描／共用契約CI→linux/amd64 build與合成runtime smoke→private GHCR版本／git標籤→production job轉推Fly同映像
- production token不進build；新增migration未有私有備份方案就拒絕自動部署, guard兩測試通過
- 官方actionlint1.7.12校驗碼驗證與workflow靜態檢查通過；本機候選runtime路由／秘密排除／mysqlnd三秒設定通過, 不當作CI amd64已驗
- Gitleaks掃346commit／16.93MB無秘密, 精準忽略既有測試向量, 沒排整個test。GHCR與Fly實際發布尚未執行, 等最後整批唯一tag

- 再核對CLI help：deploy wait-timeout為duration字串, 已改5m而非無單位300, 避免最後發布才遇參數錯誤
- 整批後端435案例／18319斷言通過, 1個環境條件略過；Controller建構SQL回歸已在TT-354以84a5dc7修正
- 使用與核准基準相同Chrome157後完整視覺比較通過, 不改門檻；先前預設Chromium153造成原生驗證訊息差異, 不算產品回歸
- 前端首輪58支通過、6支失敗、5支需專用拓樸未跑；後續board-query／board-ux單獨重測通過, inline-mail改用其要求的隔離inline配置通過, markdown回歸已由TT-353修正e59f623
- 綜合browser後續重測揭露既有測試登入欄位誤用確認密碼, 已更正回Email與原合成成員信箱, 不改產品登入驗證
- 後續重測：看板query／UX、inline寄信回饋、紙條Markdown與邀請制註冊都通過；註冊用自己的短暫setup13209雙API, 完成後已移除並還原配置
- 正式DB已建立私有0600 single-transaction備份, 未還原或修改任何資料；App範圍deploy token可唯讀SSH, 30個migration均已套用, 本批schema不變
- R2下一次發布先維持原來源, tag上線後同一映像短暫關閉HTTP服務、確認無上傳中引用, 補複製再還原HTTP與切目標, 避免複製期間有新寫入
- 綜合browser修正登入欄位後整段通過；前端共64支通過, 5支專用拓樸未跑, 限制與後端／視覺結果已整理docs/batch-release-validation-2026-11-14.md
