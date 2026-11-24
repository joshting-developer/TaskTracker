# 長文字編輯與附件操作契約

長文字以共用 `MarkdownEditor` 編輯, `MarkdownContent` 安全呈現。短標題維持純文字。使用者HTML不作可信內容, 不載入外部Markdown圖片, 私有圖片只由既有附件授權端點讀取

| 場景 | 字數上限 | Markdown編輯／預覽 | 圖片／PDF／ZIP | 草稿責任 |
| --- | --- | --- | --- | --- |
| Issue描述（建立／編輯） | 6000 | 共用編輯器 | 圖片嵌入內文, PDF／ZIP附到卡片 | IssuePanel保留表單與未完成上傳, 關閉／切換需確認 |
| Issue留言（完整頁／快速編輯） | 2000 | 共用編輯器 | 圖片嵌入內文, PDF／ZIP隨送出綁定 | 留言元件保留文字與上傳, 失敗不清稿 |
| 紙條建立／內容編輯 | 6000 | 共用編輯器 | 圖片嵌入內文（紙條私有, TT-294）, PDF／ZIP不接受且保留文字 | TodoHome／TodoDetailDrawer保留記憶體草稿與離頁確認 |
| 紙條回覆／傳遞／完成附言 | 2000 | 共用編輯器 | 同上, 只能引用自己上傳的圖片 | TodoDetailDrawer保留reply, 傳遞失敗保留草稿 |
| 紙條轉卡 | 6000 | IssuePanel沿用Markdown原文 | 含圖紙條在圖片複製完成前明確拒絕轉卡, 不因選Project開放紙條附件 | 沿用既有單向轉卡與revision／requestId |

所有可編輯場景支援粗體、斜體、清單、待辦、連結與程式碼, Ctrl／Command+B、I、格式復原／重做。IME組字時不套格式, 預覽切換不清除原文。錯誤使用可讀提示, 不以格式文字或失敗上傳覆寫草稿

附件沿用 `useAttachmentUpload`：貼上、拖放、選檔共用上傳狀態與檢查, 未完成或失敗上傳阻擋送出, 可取消／重試；圖片引用不存Base64, PDF／ZIP綁定卡片後提供下載。圖片預覽／縮圖／放大與撤權由既有 `AttachmentImage`／附件讀取API負責。紙條不能以Project可讀資格借用附件權限

草稿目前只在元件記憶體內保留並由離頁確認保護, 不承諾重新載入／關閉瀏覽器後復原, 不將私人內容寫入localStorage。附件保留既有pending TTL與冪等綁定, 不在本契約重做R2或新增儲存服務

## 紙條私有圖片（TT-294）

獨立 `todo_attachments` 表, 不放寬 Issue 附件的 Project 外鍵。Session 端點 `GET /api/v1/todo-attachments/limits`、`POST /api/v1/todo-attachments`（只收圖片, requestId 冪等）、`GET /api/v1/todo-attachments/{id}/content`。暫存只給上傳者、24 小時逾期由既有清理流程清除；建立紙條、建立人改內容與經手者附言在同交易依 `attachment:` 引用綁定, 只能綁自己的暫存。已綁定圖片只給能讀該紙條的經手者（建立人、原承辦、目前承辦、曾經手）, Project 讀權或工作區管理角色不能借用。含圖紙條轉卡在圖片複製完成前明確拒絕。前端以 `TODO_ATTACHMENTS` 範圍沿用 `useAttachmentUpload`／`AttachmentImage`／`MarkdownContent`, 上傳中或失敗時不送出
