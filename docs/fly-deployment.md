# Fly首次部署（TT-323）

## 現況

- App：tasktracker-josh, 本人joshting.1999@gmail.com帳號的personal組織, 東京nrt, 1 shared CPU／512MB
- 使用者指定的josh-90在建立App時回不存在, 依本次「決策先採建議方案」授權使用同帳號唯一可見組織, 差異已發Josh紙條, 不另建組織
- 正式入口：https://task-tracker.josh-ting.com；Josh已設DNS, Fly憑證Issued且HTTPS正常。https://tasktracker-josh.fly.dev保留相容入口, 兩者共用同一正式資料庫
- 首次映像：registry.fly.io/tasktracker-josh:git-0f1f1b4, digest sha256:6d8d17c91e5c2c6e98842249c0747ab808778ab7108b9ea888938624f7b5fca7
- 首次以Fly remote build發布, 沒有push／Git tag／GHCR發布；TT-320的CI仍未完成, 不將本次視為CI驗收

## 發布與設定

使用專案忽略目錄.local/flyctl/bin/flyctl（本次v0.5.10）, 先讀共用06-deploy/fly-io。原系統CLI為2023年版, 登入狀態不相容, 已由Josh核准新版官方登入

fly.toml包含正式一般設定、CA的files掛載、HTTPS、自動停啟及release command。Secrets只以私有0600檔案stdin匯入；特殊字元須依dotenv語法引用, 第一輪未引用造成Redis認證失敗, 引用後同映像重新套用已通過, 未修改Redis原密碼

正式MySQL為task_tracker, Redis命名空間tasktracker:production:, 兩者驗CA與主機名稱。Session為database／secure／host-only, 公開註冊依TT-347改為開放並保留信箱啟用, mail inline／queue sync／broadcast null, 附件r2-attachments。TT-162重新分析後建議初期只在有需要時人工維護, 不定時喚醒Fly；沒有自動清理期限保證, 不宣稱已有外部觸發／常駐worker或Reverb

## 驗證

已實測一次Machine停機→HTTP自動喚醒, health 200約5.43秒, 原PAT可繼續讀卡；這是單次結果, 不是p95或完整TT-154成本驗收

Fly release migration成功；HTTPS health、實際Machine MySQL TLS／Redis TLS、正式設定及1440／768／390px登入頁通過。TT-321已驗新正式映像、專案冒煙與核准視覺比較, 這次沒有更改產品元件

隔離後端429項18155斷言通過、1項既有skip。預設128MB兩次因大批fixture／全量測試查詢不足, 最後只把Codex測試容器CLI上限設512MB, 清掉自身合成測試schema後通過, 沒有改正式runtime記憶體限制。沒有宣稱frontend全部工具皆通過, 先前單支browser-check編輯器關閉及特殊環境項目仍未完整回歸

資料同步由TT-341依Josh本次授權完成44表兩輪完整比對, 19帳號／462卡片等原資料與PAT保留。遷移後Codex登入、專案／看板／三尺寸卡片詳細頁及R2 PDF上傳／綁卡／下載雜湊／匿名拒絕通過；原庫唯讀與私有備份保留；搬移工具／回復限制見[搬遷手冊](mysql-transfer-runbook.md)

## 後續部署與回退

後續先跑consumer規定的驗收, 以固定commit標籤或已驗證digest發布, 不用latest；migration前備份實際庫。首次沒有更早的已知良好程式版本, 尚未宣稱真實前版回退演練完成

回退映像不還原資料, 必須相容目前schema；正式恢復寫入後不能直接覆蓋本地舊快照。若需回原庫, 先停寫／備份新資料並處理差異, 不直接重啟本機舊服務讓兩邊各寫各的

DNS已生效並經fly certs check驗憑證, APP_URL已切正式網域且驗登入。ALLOWED_ORIGINS只保留實際使用的origin；不因DNS缺資格嘗試用R2憑證修改DNS

## 線上與本地的分工

- 日常工作：線上TaskTracker是唯一進度來源, 包含卡片、紙條、留言、指派與正式工時；Codex／Claude工具使用各自私有設定的線上baseUrl
- 本地開發／測試：程式碼在本地修改, 用各Agent隔離Compose、MySQL及合成資料驗證, 不把測試資料同步回正式庫, 不使用正式帳號／PAT跑fixture或回歸
- 保留的原本機MySQL：這是搬移前的唯讀備份, 不是可以seed／reset的測試庫；不解除super_read_only來方便測試。要另開本地開發服務先配置獨立新庫, 不直接重新啟動舊Compose

線上暫時不可用時先保留工作草稿, 不回舊庫記另一份進度；恢復後讀最新revision再補登。具體隔離埠及測試命令以專案tasktracker-test-environments Skill為準
