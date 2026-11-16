# GPT Bridge 直接寫入

TT-413, Josh 指定非刪除操作不需要預覽及第二次確認, 已部署第5版到 https://tasktracker-joshting-bridge.joshting-1999.chatgpt.site

19個非刪除寫入工具直接執行, 不要求confirmation, 不做Adapter額外版本預讀。工具攜帶唯一body.requestId, 同一操作重送沿用原值與相同內容；正式API保留revision、scope、即時權限、租戶及重播授權。遇衝突回報, 不自動覆蓋或重試

delete_issue與delete_tag仍需精確目標預覽、明確確認與有期限的簽章票據。此流程變更不代表批次修改所有卡片的分類授權, 不新增批次核心API

83項Adapter測試、build、正式0.24.0的47個operation覆蓋及Skill／資料存取證據通過。部署appgdep_6afabdb6d7848191a266146201754794已succeeded, source subtree d827848443f4c2fb2f5545f4609e5e15510ff05b, env revision20, 原owner與Secret保留

既有對話可能仍保有舊「先預覽」工具描述, 請新對話載入新版說明後操作。沒有用正式業務卡片做直接寫入測試, Host新流程待實際使用驗收, TT-413保持review
