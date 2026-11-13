# 留言圖片（TT-293）

完整頁與編輯抽屜的留言沿用MarkdownEditor, 可貼上、拖入或選擇PNG／JPEG／WebP, 並在預覽中查看私有圖片

- 留言原稿仍限2000字, 圖片只存attachment:UUID引用, 不存base64
- 上傳與失敗重試沿用原requestId；上傳未完成或有待處理失敗時禁止送出
- 失敗／取消上傳保留原稿, 未送草稿與未完成上傳有離開保護；成功送出才重設圖片佇列
- Session與PAT留言共用IssueService → AttachmentService → AttachmentRepository；同交易綁定本人同專案未過期pending圖片, 失敗時留言與Issue revision一起回滾
- 可重用同卡active圖片, 不可偷用其他卡片、其他專案或其他人的pending圖片；圖片存取仍每次驗目前資格
- 一張卡的附件配額仍合併計算描述與留言, 不因每則留言重新給額度；未引用pending仍由既有過期清理處理

API仍用既有留言端點, 不增加查詢或上傳路由。留言列表每頁20筆, 圖片依實際渲染按需讀取, 不預載其他活動或整個Workspace

PDF／ZIP另由TT-295處理, Josh已確認PDF第一版只提供下載。後續選項先採建議做法, 需複查時用小待辦交給Josh
