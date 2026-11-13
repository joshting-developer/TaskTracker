# TT-278 搜尋路徑

- 保留預估300分鐘, 同Agent只領本卡；額外交辦的TT-310定位器與共用基準已確認並回填
- 完整卡號採等值路徑, 明確尾端*採卡號prefix, 其他維持原contains語意；text:前綴可強制原文字包含搜尋
- SQL、看板本地篩選、選項與公開API文件同步, 中文／英文／短字／標點／%_不因FULLTEXT而消失
- 真MySQL實測舊contains與卡號等值、ngram候選與寫入成本, 不因有FULLTEXT就更換所有文字搜尋
- 受影響HTTP／租戶隔離／前端查詢實測, 更新共用candidate／compare基準後提交, 填本人時間／卡片／小待辦
