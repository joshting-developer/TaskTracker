# TT-321 正式環境設定

- 依Josh部署授權, 先完成正式設定與秘密清單, 再接版本映像與Fly部署；預估沿用120分鐘
- 使用共用工程規範與Ponytail lite, 讀取實際runtime／Laravel設定；只在隔離服務驗證, 不改現用資料
- 一般設定與Secrets分開, TLS驗CA／主機名稱、production Redis命名空間、同步mail及null broadcast、R2私有儲存與關閉公開註冊
- 秘密不輸出／不提交；APP_KEY沿用本地現用, 正式庫仍空庫部署, 資料移轉另由TT-341取得當次授權
- 完成設定檔、隔離正式映像啟動及冒煙, 提交並回填工時與限制
