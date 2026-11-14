# TT-162同步寄信與維護執行

## 前期配置

正式設定採`MAIL_DELIVERY_MODE=inline`、`QUEUE_CONNECTION=sync`、`BROADCAST_CONNECTION=null`。信件預設inline, 不需要常駐worker或每分鐘mail:dispatch；需要本地Queue驗證時才明確設`MAIL_DELIVERY_MODE=queue`, 沿用原本Queue設定與重試

正式不啟Reverb, ProjectSignals在非reverb直接return。不能把queue worker不存在當成已停廣播, 要從觸發端不建立工作。Redis仍可能供cache／節流／Session使用, 不因mail不排Queue就移除或flush共享Redis

## 寄信的提交與失敗

先提交帳號、驗證challenge與唯一outbox事件, 再在相同請求的afterCommit階段交付SMTP。SMTP連線不持有業務DB交易或鎖；預連線與傳送之間仍重驗當次收件帳號、地址、工作區資格與challenge有效性

SMTP維持require_tls與stream timeout 10秒, 這是傳輸讀寫等待上限, 不是宣稱整個HTTP請求保證10秒內完成。錯誤原文、憑證與信件內容不回傳前端或寫進Queue失敗紀錄

- 成功標sent, 清加密payload；同事件不再交付
- 連線失敗在inline直接標failed並清payload, 不等worker自動補寄
- SMTP可能已收信但結果不確定時標uncertain, 不自動補寄, 避免重複信件
- 註冊仍回pending Session, 不因已commit後的SMTP錯誤讓人重新建立帳號。回應`deliveryStatus`取提交後最新安全狀態, 不回內部errorCode／recipient識別或秘密
- 登入頁顯示未寄出或結果不確定, 使用者仍可輸入收到的碼、修改信箱, 或等既有60秒冷卻後按重寄。重寄明確建立新的challenge並撤銷舊憑證, 保留限流

Session機器規格更新至1.2.0；新欄位只屬待驗證回應, 舊登入資格與PAT權限規則不變。測試Compose與PHPUnit明確使用queue合成fixture, 不發真信；inline用fake連線與Mail另行覆蓋成功／失敗／rollback／重送

## 必要維護CLI

`php artisan tasktracker:maintenance`執行既有有界附件清理, 每輪最多1000筆；每天其中一輪加`--prune`清90天前的HttpOperationLog。不寄信、不派背景Job、不開公開HTTP維護端點

使用cache prefix下的900秒互斥鎖, 遇到其他工作執行時回busy並非0退出, 不搶鎖；執行者必須限制整個程序少於900秒。清理失敗或prune非0退出也回非0, 結果與log含state、purged、failed、pruneExitCode、durationMs, 不含業務內容或連線秘密。finally釋放自己的鎖, 失敗附件仍保留purging供下次重試

原附件每小時／prune每日Schedule宣告保留供本地或既有Scheduler環境；正式外部執行者直接觸發maintenance, 不需要常駐schedule:work, 也不能同時兩套重複排程

## 2026-11-14重新確認：先不定時喚醒

Josh要求重新分析後, 目前建議採人工維護, 不建立每小時喚醒Fly的CI排程, 也不新增worker／Cron Manager。信件已inline, 廣播已null, 兩者都沒有必須靠定時程序才能完成的工作

程式仍有兩項清理用途：未完成上傳／過期pending／已刪除附件的實體物件回收, 以及90天前HttpOperationLog清除。這些不是寄信排程, 暫不執行不會讓登入／寫卡／寄信等核心流程停住；過期與已刪除附件的存取仍由API即時拒絕, 不靠清理才隱藏

初期在附件空間或操作紀錄累積、有實際維護需求時, 經確認再由維運透過既有SSH執行tasktracker:maintenance, 需要清紀錄才加--prune。CLI本身會真的刪除符合條件的物件及紀錄, 不拿正式庫做演練, 這次沒有執行

限制：沒有定時觸發就不保證物件在期限一到立即刪除, 90天是prune的篩選門檻, 不是目前已啟用的自動保留上限。資料／儲存會持續累積, 有明確期限、用量或合規需求後再配置低頻自動維護。失敗仍以CLI非0退出、purging狀態與log查詢, 由維運明確重試；沒有自動監測就不能宣稱會自動通知

Fly App目前已存在, 本節取代下方「App未建立所以待配置每小時CI」的前期建議。部署與網域已完成, 舊的外部定時喚醒實測條件保留為未來選用自動維護時的檢查清單, 不為了讓該清單完成而定期啟動機器

## 前期自動維護提案（未採用）

目前沒有TaskTracker Fly App／Machine, 因此沒有安裝或啟用雲端排程、沒有測機器停機後的喚醒、外部退出碼與費用。這是TT-162的剩餘部署條件, 程式測試通過不能取代它

建議沿用repository的CI作外部定時觸發：每小時一次附件清理, 每日一次帶--prune。由Secrets提供app-scoped Fly Token, 明確設定App／Machine, 限制整輪10分鐘, 失敗保留CI紀錄並通知維運。只喚醒既有HTTP Machine, 不新開常駐worker, 不任意停止可能正服務使用者的Machine；空閒停止仍須由HTTP Machine的Fly autostop配置驗證

Fly官方[排程方案](https://docs.fly.io/blueprints/task-scheduling)也包含獨立Cron Manager或Supercronic程序, 這次沒有採用需另有常駐程序的做法。[SSH console](https://docs.fly.io/flyctl/cmd/fly_ssh_console)只連執行中的實例, 不能假設停機時直接SSH就能完成工作

本機目前fly CLI為0.0.475, 參數與最新文件有差異（ssh console沒有--machine）；正式配置時需先更新並核對CLI、再選可靠的指定Machine執行入口, 不能照貼最新版命令當成已實測。App建好後須實跑：喚醒→維護成功／失敗非0→查執行紀錄與通知→空閒停止, 並觀察實際短時運算／外部SQL／物件請求成本

## 程式階段驗證

隔離後端403項17093斷言通過（1項既有skip）；最後current查詢收斂後另跑57項525斷言, 正式inline＋null組合另15項148斷言通過。真Chrome inline失敗／pending重整保留／冷卻／三尺寸與專案冒煙通過, 69張正式視覺比較通過, 沒寄真信

共用登入的完整瀏覽器腳本回歸仍在執行, 已知舊專用埠guard／API最小回應斷言有失敗, 不能宣稱所有frontend/tools通過。雲端維護觸發仍依前節列為未完成, 不因程式提交就結案TT-162
