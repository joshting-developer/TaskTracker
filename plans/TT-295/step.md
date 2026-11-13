# TT-295 進度

- 已讀最新需求並保留原預估240分鐘, 只有本卡inprogress
- 已完成PDF／ZIP獨立MIME／副檔名／大小／配額, ZIP只讀目錄驗證壓縮比、總解壓大小、路徑、加密與symlink；不解壓或執行
- 已完成完整頁按需附件分頁、上傳重試、綁定版本衝突保留pending與授權下載；PDF／ZIP不內嵌Markdown
- OpenAPI 0.18.0與契約範例同步, 圖片mimeTypes不變, 一般附件從limits.general取得獨立規則
- MySQL與正式契約25項／1075斷言通過, Chrome一般附件／重試／重整／鍵盤與三尺寸通過, API client測試及build通過
- 安全政策明列download-only-structural, 不宣稱防毒掃描或PDF內部語法驗證；交付後請Josh用小待辦複查
- 本機Compose補resources唯讀掛載, 避免app熱載新碼卻仍提供image內舊OpenAPI；已更新主後端, 通知CLI可讀取本人通知, hasMore=false
- 一般附件及留言圖片Chrome回歸通過, 48張正式視覺比較通過, 正在整理Skill證據與交付
