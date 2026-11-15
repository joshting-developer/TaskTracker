# TT-349 唯讀私人Adapter

沿用既有估時, 先完成合成驗證再私人發布, 不以原Agent PAT接入。只修改integrations/chatgpt-bridge, 不改Laravel業務

1. 由正式OpenAPI抽取8種唯讀operation的參數及回應欄位, 固定origin、路徑白名單、單頁上限20與深分頁限制
2. 加入Sites MCP協定及可信owner身分檢查, 所有非協定／工具欄位拒絕, 不提供任意代理
3. 合成測試成功、空頁、401／403／404／429、逾時、redirect、過量回應、Secret遮蔽及惡意正文只作資料
4. 本地測試、artifact及資料存取審查通過後提交push, 回填工時。TT-350才驗私人發布／安裝與正式唯讀連線
