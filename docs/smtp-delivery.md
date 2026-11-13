# 寄信（TT-189／TT-162）

2026-11-14起預設改為commit後inline, 完整執行方式見[TT-162](synchronous-mail-and-maintenance-2026-11-14.md)。以下Queue是可選開發模式, 不是正式前置條件

使用Laravel Mail, 目前提供固定本機測試信與供TT-319串接的verification用例, 尚未寄送狀態變更／被指派等通知信

## 設定與本機程序

backend/.env的MAIL_*為主要來源, SMTP_*只作沒有MAIL_HOST時的舊配置fallback；verify與實際寄信都使用config/mail.php, 不混用兩個主機的帳密

Docker不直接載入整份backend/.env, 避免覆蓋原本APP_KEY或資料庫配置。後端啟動後執行：

```sh
python3 tools/sync-mail-env.py
docker compose up -d --no-deps backend
```

工具用Laravel原生dotenv解析器讀取MAIL欄位, 生成忽略且0600權限的.local/tasktracker-mail.env, 根.env只記其選擇路徑。私有快照用base64避免不同Compose版本改寫特殊字元, base64不是加密；檔案與容器環境都視為秘密, 不得提交或輸出。設定修改仍改backend/.env, 再同步並重建後端／Queue, 不手改快照。原APP_KEY與DB設定保持

預設MAIL_DELIVERY_MODE=inline, 不需mail Queue或每分鐘重派。明確選queue開發模式時才需要worker訂閱default,mail；必要附件／紀錄清理由外部定時執行maintenance CLI, 不是要求正式常駐worker。Fly外部觸發尚未配置, 不能只啟HTTP就假定維護有在跑

## 寄送狀態與重試

- pending：已保存, 等待commit後交付；queue模式才等worker或人工重派；每次恢復查詢最多100筆, 只讀事件ID
- sending：請求或Worker已取得本事件寄送權；同事件或重複Job不再取得第二次權限
- sent：SMTP接受, 只記時間與次數, 清除加密傳輸內容；不等於已進收件匣
- failed：inline連線失敗即停止；可選queue模式才重試三次或Queue耗盡；不無限重送
- uncertain：送出途中斷線或Worker中斷, 無法確認SMTP是否接受, 不自動補寄以避免重複
- skipped：寄前帳號、地址、有效成員、期限或驗證狀態已改變, 不送舊信

資料庫唯一事件鍵及User→MailDelivery鎖序保護並行, 內容與地址入庫前加密, Job只序列化ID。SMTP連線及寄送都在資料庫交易外, commit後才交付或排工作；inline連線失敗不自動重試, 驗證畫面保留pending Session與明確重寄。queue模式broker失敗保留pending且不使原業務API變500, 明確未寄出的連線失敗才延遲30秒／120秒重試, 原SMTP錯誤不寫入failed_jobs或log, 只記固定安全錯誤碼

SMTP和資料庫之間沒有共同交易, 不能宣稱跨系統exactly-once；結果不明時需要人工確認, 不能盲目用新事件鍵補寄。人工恢復操作可執行（不定時自動補寄）：

```sh
docker compose exec backend php artisan mail:dispatch
docker compose exec backend php artisan mail:status <outbox-id>
```

## 測試與界線

自動測試全部使用Mail fake及假連線邊界, 包含真DatabaseQueue worker與雙MySQL程序競態。手動實寄另受backend/AGENTS.md限制, 必須有當次明確授權, 不能因這份文件存在就寄信

本輪Josh明確授權一封測試信, SMTP於2026-11-13 20:54:42接受, attempts=1, 並已由Josh確認收件。沒有寄送第二封, 不將這次例外當成未來授權

新增目的種類須另定收件人資格。TT-319已接上當次challenge資格, 重新寄碼／改信箱會撤銷舊challenge及尚未交付的事件。信件不能包含密碼、PAT或SMTP秘密；短期註冊驗證資訊屬TT-319的明確交付目的, 仍只經加密暫存傳輸, 不放日誌

Laravel的Queue與commit行為參考[官方Mail文件](https://laravel.com/framework/docs/mail)及[官方Queue文件](https://laravel.com/framework/docs/queues)

每次人工恢復命令最多檢查100筆逾60秒的sending, 改為uncertain並清除內容；再重派最多100筆到期pending。兩種查詢都有狀態／時間／ID索引與穩定排序, 不掃描整張表
