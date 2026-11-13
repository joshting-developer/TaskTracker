# TT-189 進度

- 已估時180分鐘並領取, TT-319維持open
- 已完成加密outbox、唯一事件鍵、寄前資格重驗、交易後派送、有限連線重試、uncertain不補寄、broker恢復批次與安全狀態命令
- MAIL_*單一配置與舊SMTP_* fallback完成, Docker同步工具不覆蓋APP_KEY／DB；特殊字元與0600權限合成測試通過
- 本機後端、Queue與Scheduler已更新, 只在Josh明確授權後寄出一封測試信；20:54:42被SMTP接受, attempts=1, Josh已確認收件
- backend/AGENTS.md保留原限制, 沒有將單封授權擴大為未來寄信授權
- memory／MySQL及分層28項203斷言、真正DatabaseQueue消費與雙MySQL程序競態通過, SMTP邊界不持有DB交易
- 完整後端338項／15780斷言, 1項依環境略過；整包測試用512MiB（實際175MiB）, 隔離Origin補齊5175／5291後通過。前兩輪128MiB或Origin配置不足的失敗紀錄保留, 不算通過
- 首次視覺比較缺少Playwright瀏覽器未通過；指定核准基準使用的Chrome後48張比較通過, 沒有更新基準；sending恢復索引已增量套用, Queue與Scheduler已重啟
- 正式工時已填55分鐘, Skill證據通過後提交與結案
