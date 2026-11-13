# TT-289 詳細頁閱讀精修

- 小待辦 ee0632cf-a79d-44e4-a600-bb9f5f34ecef, 保留預估240分鐘, 只領本卡
- 既有 IssueDetail 保留唯一資料來源與各活動分頁, 標題與狀態放同一閱讀區, 描述／圖片為主, 右側屬性更緊湊
- 沿用 MarkdownContent／AttachmentImage 的安全 resolver與檢視器, 不另建附件API或快取
- 活動 loading／空／錯誤與分頁放回活動區, 避免載入中先顯示無資料；不動同時由其他Agent修改的小待辦
- 真實隔離 MySQL／Laravel＋Chrome 驗圖片、深連結、活動按需、鍵盤、草稿與三尺寸；保存修前後與正式比較, 回填工時／狀態後才完成小待辦
