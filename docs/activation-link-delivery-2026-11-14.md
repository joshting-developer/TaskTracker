# TT-338 單一啟用連結與原分頁同步

Josh在TT-319驗收紙條回報, 信件連結開新分頁後雖然能啟用, 原註冊分頁仍停在驗證碼畫面。這次移除一般驗證碼流程, 只保留啟用連結

## 實作範圍

- AuthGate保留重寄、修改Email及寄送失敗提示, 不再顯示驗證碼欄位
- 信件只寄一次性連結, 正式Session OpenAPI改為token輸入
- 啟用後以無帳號、無憑證的storage訊號通知其他分頁；原分頁取得焦點或重新顯示時也會重驗
- current只信任伺服器pending Session中的本人ID, 以已啟用、已消耗且未過期的當次challenge恢復資格, 再走原Workspace Session授權與Session換號
- local／testing的local666只供既有隔離fixture, 一般驗證碼已拒絕, production／staging不接受code欄位；沒有另加測試HTTP端點
- 舊資料表code_hash欄位暫留, 寫入不可使用的隨機值, 不重新開放舊碼

## 資料存取審查

1. 呼叫端只需要pending狀態的Email、到期、重寄冷卻與安全寄送結果, 或已登入的最小身分及feature開關；不需要專案、任務或活動清單
2. 這不是列表；只查Session指定的一個User、當次EmailVerification及必要的單筆MailDelivery狀態
3. 以主鍵User ID、唯一user_id、token_hash等索引在SQL定位, 不先載入全部帳號後過濾
4. 不取得整個Workspace, 已登入資格沿原WorkspaceSession重驗
5. 不讀卡片詳細、歷史、留言或工時
6. 沒有新增背景輪詢, 僅pending分頁的焦點／顯示／啟用訊號觸發current, 同一元件single-flight。未啟用分支重用本次已讀的User與challenge, 不再重複查兩次；成本隨本人驗證紀錄定位, 不隨專案或卡片數量成長
7. 雙引擎HTTP測試驗未啟用不放行、本人當次啟用才恢復、其他帳號不放行、到期與重播拒絕。瀏覽器驗原分頁同步及重新整理, 不用SQL次數代替資格範圍
8. 本次沒有宣稱整體效能驗收；隔離MySQL與瀏覽器屬本地功能驗證, 不是遠端部署p95或RUM。既有回歸缺口仍由TT-336／TT-334追蹤

## 呼叫與安全邊界

AccountSessionBoundary → EmailVerificationService → EmailVerificationRepository → User／EmailVerification／MailDelivery。穩定協作者維持建構子注入, transaction owner保留既有HTTP邊界與Session lease；Service內交易為原驗證用例協調, Repository只存取資料

storage訊號不含身分, 也不能作為授權。連結GET沒有啟用副作用, 按下啟用帳號才送POST。原分頁與啟用頁使用不同瀏覽器或host時, 原pending Session仍須回伺服器重驗, 不依賴共享Cookie或localStorage

## 驗證記錄

測試日誌、候選圖片與詳細證據放在忽略的artifacts/tt338, 只用隔離合成資料與無寄送Worker的佇列環境。SMTP不重測, 不對現用資料庫seed或修改既有帳號驗證狀態

本地完整測試與畫面比對結果以plans/TT-338/step.md及卡片回填為準；沒有發佈或推送
