# 受控資料庫遷移

## 發布與遷移分開

正式HTTP使用只能存取TaskTracker資料的runtime帳號, DDL由獨立migration帳號執行。tools/fly-migrate.py以無服務映射的一次性Machine執行受測版本的deploy/migrate.php, 帳密走stdin, 不放HTTP環境或建置映像

自動tag發布先執行tools/check-release-migrations.py。出現未核准的pending migration就停止, 不能略過或把正式seed當初始化；需先完成私有備份、遷移方案與獨立套用, tag內的migrate才會是no-op。HTTP冷啟動不跑migration

## 有schema異動時

1. 讀正式migration清單, 比對候選來源並固定本次檔名與commit。逐項確認新增／破壞性欄位、backfill、鎖與舊映像相容性
2. 使用私有0600連線檔及嚴格TLS建立single-transaction完整SQL備份, 記檔案大小與SHA256, 不上傳公開CI產物。MySQL DDL與多個migration不能假設整批原子回滾
3. 備份還原到本次新建、無公開埠的MySQL副本。只在副本做up／down／up及失敗驗證, 比對原有業務欄位、筆數與內容雜湊, 檢查新表、預設值及所有外鍵。正式禁止seed／reset／down測試
4. 正式執行前另存最新備份, 用migration專用帳號與明確檔案清單執行。新增欄位／空表且舊程式相容時可保留舊HTTP映像, 不讓候選HTTP提前讀新schema
5. 核對已執行清單、外鍵、原有登入與PAT, 再恢復一般服務並進整批tag流程。資料仍有新寫入時, 要將已授權的同時業務異動與遷移修改分開核對, 不能把整表hash不同直接當遷移失敗或直接覆蓋備份

## 失敗與回退

- 遷移失敗先停發布, 記錄已完成的migration與實際schema。若MySQL已提交部分DDL, 依該次清單修復並重新驗證, 不盲目重跑所有歷史migration
- 只新增欄位／空表且舊映像相容時先維持原HTTP版本, 不為了回退映像刪表或移除新資料
- 破壞性變更或要還原整庫時必須另有明確授權與確實停寫保護, 核對備份後新增的資料, 禁止拿舊快照蓋掉新進度
- 本次Fly Proxy在autostart=false、cordon及無services時仍曾喚醒舊Machine, 不把CLI停機的一次讀回當長期停寫保證。需要排除所有寫入的操作, 必須再核對資料端與所有寫入者；否則不能宣稱停寫成功

## 2026-11-15 本批結果（TT-153）

正式MySQL實際版本8.0.46, 原30項migration, 本次新增5項：password recovery guards、workspace creation requests、account profile requests、invitation decision/sender、workspace member status events。副本up／down／up後, 原43張業務表所有既有欄位筆數及內容雜湊一致

正式5項均已套用, migration清單為35項, 79組外鍵沒有孤兒資料。正式整表對比期間有Claude新增卡片／收錄看板等正常業務操作, 稽核事件可對應, 因此不宣稱正式43表byte-for-byte不變；沒有用備份覆蓋這些新進度。原HTTP設定、HTTPS健康及Codex PAT已恢復正常

本次沒有正式down、seed、額外寄信或改HTTP帳號權限。備份、帳密、原始檢查與具體ID都留忽略的.local/tt153, 原庫與Redis沒有清空。這是schema準備完成, 新功能還要隨本批版本部署才會上線
