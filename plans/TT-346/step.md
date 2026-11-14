# TT-346 進度

- 已讀Josh同意保留方案, claim後開始唯讀清點與權限檢查
- TT-320流程已提交但main push被自動審查阻擋, 此卡只準備資料／配置, 最後同一tag發布

- 正式儲存headBucket成功, 原服務仍指開發位置, 清點到3個active正式附件
- 先逐個來源byte_size／SHA256符合DB, 目標不存在才copyObject, 目標再驗bytes／SHA256, 3筆全部通過且來源保留
- 僅stage正式儲存Secret, Machine映像未變, 私有發布設定同步並保存舊備份, 開發設定不變
- 最後tag前須再清點／補複製, tag後驗全部active引用與新上傳；不能把這次快照當切換完成

- 本人PAT讀三個既有附件皆符合DB的bytes／SHA256, 匿名均拒絕, stage沒有切掉目前來源
