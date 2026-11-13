# TT-280 父任務換Epic的子卡讀寫

- 已估300分鐘, Codex已領取。先等待TT-277核准後補完視覺比較與結案, 本卡實際開發時間從該階段完成後計, 不重複填報等待期間
- 盤點原本逐子卡完整require／put載入留言與歷史的路徑, 保存1／20／100子卡的隔離MySQL成長量測
- 新增最小子卡投影與批次資料寫入, Service產生revision／異動事件, Repository只更新Epic、版本、時間並追加歷史；保留同一交易與Workspace鎖、receipt與撤權重驗
- 驗同Project／Workspace範圍、revision／歷史保留、無變更不追加、rollback與重播；不更改HTTP回應或前端互動

- 正式本人實際工時22分鐘已填, 不含先前TT-277驗收等待；本卡來源指紋與backend／資料存取／量測／Skill完整性證據由check_evidence.py驗證通過, 同時進行中的卡仍僅本卡
