# 執行紀錄

- 已讀紙條完整留言／交接, 分享預覽與部署設定清單已完成；多語系依卡片要求排在部署後
- TT-357完整本地回歸已closed, 76支前端／492項後端及141張視覺比對已驗, source c8ef968之後backend／frontend／docker／fly.toml無差異, 不為純紀錄commit重跑相同整套測試
- TT-153原open未指派, 已填90分鐘並依規則claim；先準備正式migration方案, 尚未改正式schema
- 中斷後先核對程序：正式尚未改schema, 實際是5項pending（含workspace creation requests）, 已校正計畫。副本啟動檔案命名碰撞導致未取得MySQL root密碼, 只影響本次副本；已分開mysql-clone.env與Laravel clone.env後重建自己的副本
- Josh最新要求本次先完成多語系再整批部署, 部署後才接GPT串接, TT-308排入本次發布
- 完整私有備份已還原, 副本5項up/down/up通過, 原43張業務表既有欄位hash與筆數一致
- 正式以migration專用帳號完成固定5項, 35項清單與tag發布guard通過, 79組外鍵無孤兒
- 正式比較發現audit_events／board_issues／boards／issues及receipt/log資料有同期Claude操作, Fly Proxy仍曾喚醒無services機器；如實記錄資料端未完全停寫, 沒以備份覆蓋新進度, 不冒稱正式全表不變。所有migration只新增欄位／表與已審閱的新欄位backfill, 舊映像相容
- 原正式Machine設定與服務已恢復, HTTPS health與Codex原PAT正常；正式引擎為8.0.46, 已修正文檔把本地8.4誤寫為正式版本的敘述
- 工具up/down/up只在私有備份副本執行down, 正式從未down或seed。操作與失敗回退流程已整理docs/controlled-migrations.md
