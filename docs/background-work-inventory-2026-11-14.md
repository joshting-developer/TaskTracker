# TT-142背景工作盤點

## 已確認的前期方向

Josh決定先不為寄信常駐Queue worker或每分鐘跑Scheduler。信件改在API請求內、資料庫commit後直接寄, 必須有timeout、寄送結果與明確重寄入口。正式環境先關WebSocket, 不能讓廣播持續排進沒人消費的Queue

這份是現況盤點與執行規格, 不是宣稱TT-162已完成實作或Fly排程已建立。當前原始碼仍走TT-189背景outbox, 下列「要調整」都由TT-162接續

## 逐項處置

| 項目與入口 | 目前行為 | 前期要調整成 | 重試／觀測與成本 |
| --- | --- | --- | --- |
| 註冊／重寄驗證信 `EmailVerificationService::issue` → `MailDeliveryService::enqueue` | 加密outbox唯一事件, commit後送SendTransactionalMail到mail Queue | 請求內commit後送SMTP, 不在資料庫鎖交易內等連線；保留加密payload、收件資格重驗、單一事件去重 | 連線／傳送有界timeout；連不上回可重寄結果, 寄送結果不確定不自動再寄。使用者明確操作後建立新驗證事件, 不用常駐worker |
| `mail:dispatch`與每分鐘排程 | 每輪最多100筆pending, 回收超過1分鐘sending為uncertain再派Job | 拔掉每分鐘排程, 不能把未知交付結果自動補寄；命令如保留須明確是人工恢復, 不依賴它寄第一封信 | DB寄送狀態／安全錯誤碼查詢, 不記原SMTP錯誤或驗證秘密；SMTP交付只保證本地去重, 不能保證外部恰好一次 |
| `attachments:cleanup` | 每小時、withoutOverlapping, AttachmentCleanupService每輪最多1000筆；先鎖metadata, 交易外刪物件 | 保留必要維護, 由外部定時觸發短命令, 不綁站內HTTP流量或維護用公開API | 失敗保留purging下次再試, 命令failed>0退出非0；記purged／failed與執行時間, 需外部失敗通知。依物件儲存請求與短時間運算計費, 不聲稱免費 |
| `model:prune --model=HttpOperationLog` | 每天清90天前的觀測紀錄 | 外部定時觸發, 不移除90天保留規則；與附件維護共用短命令入口, 不開第二台常駐機器 | 用既有Laravel批次Prunable, 非0退出供外部重跑與查log；停排不影響業務寫入, 但資料持續累積, 須監測最後成功時間 |
| `ProjectSignals` → `ProjectChanged implements ShouldBroadcast` | 僅broadcasting.default=reverb才afterCommit派事件；其他driver已直接return | 正式明確BROADCAST_CONNECTION=null且前端不訂閱Reverb；驗證寫卡後jobs不增加 | HTTP重讀仍重驗資格；保留本地Reverb模式, 不把broadcast driver改log當作已關閉產品訊號 |
| `InfrastructureProbe`／`tasktracker:check-infrastructure` | 人工命令測Redis、Queue消費、WebSocket握手, 明確要求Redis Queue | 保留開發診斷, 正式worker／Reverb關閉時不可拿它當健康檢查或部署必過門檻 | 不定時觸發、不發業務事件。正式健康改看API／DB／必要cache, 不為讓診斷過關偷偷開worker |
| Session、節流、Cache／鎖 | Session可database／Redis；Cache提供節流、排程互斥等, Session另有MySQL命名鎖保護 | 不因不用Queue就移除Redis或更換Session driver；部署環境明確指定, 繼續用現有scope與namespace | Redis／DB可用性與資格重驗仍是必要依賴；不能flush共享庫。DB Session清理依既有Laravel生命週期, 不新增HTTP維護介面 |

## TT-162實作與部署的分界

1. 先落地同步寄送模式、失敗回應與驗證畫面狀態, 拔mail分鐘排程；保留開發環境可選Queue, 不由正式sync設定靜默假裝排成功
2. 正式設定明確`BROADCAST_CONNECTION=null`, 以測試確認Project寫入不產生廣播Job。現有ProjectSignals已具guard, 不為此重寫事件架構
3. 提供受控CLI維護入口與可重跑、互斥、非0退出；附件每小時、觀測紀錄每天的外部執行者另依Fly實際App／Machine建立。不能假定「有人開網頁」就有Scheduler, 也不自動用主庫跑清理驗收
4. 外部執行者可採既有CI的定時workflow, 憑證走Secrets、App／Machine使用明確設定, 未配置先停止；不放維護HTTP端點、不新建常駐worker。是否可自動喚醒、退出與費用必須以Fly實際拓樸驗證, 不能只跑本機CLI就結案雲端排程
5. 將「程式測試完成」與「Fly外部排程實測」分開回填。尚無TaskTracker Fly App時, 程式可先交付, 雲端觸發、失敗通知及費用觀測列未完成, 交Josh待辦追蹤

## 盤點方式與限制

搜尋backend/app、routes、config的dispatch、ShouldQueue、ShouldBroadcast、afterCommit、Schedule、Cache及Session使用；對照寄信狀態機、附件清理、Prunable、ProjectSignals與診斷命令, 沒有只列套件名稱

本卡只改文件與plans, 沒有寄真信、操作現用DB清理、啟動worker或建立付費雲端資源。TT-162執行後須更新本文件的現況, 保留本次盤點與新實作的差異
