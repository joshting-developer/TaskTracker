# 本地分組回歸

用同一組測試MySQL、不同資料庫跑需要不同設定的流程, 不需要另外租主機或付費服務。後端全套和一般瀏覽器測試依序執行, 不同時修改同一個測試庫

## 執行前

先依本專案tasktracker-test-environments Skill啟動自己名下的隔離Compose。Codex用tasktracker-codex-test與13199, Claude用tasktracker-claude-test與13198。原本機主資料庫和正式資料庫都不能使用

```sh
# 完整執行, 失敗或缺條件會回非0
python3 tools/frontend-regression.py --agent codex --group all

# 只看分組, 不啟動服務
python3 tools/frontend-regression.py --agent codex --group all --list

# 單組重測；Claude改--agent claude
python3 tools/frontend-regression.py --agent codex --group realtime
```

需要與既有畫面基準比對時, 用原來的Chrome版本, 另外執行tools/visual-check.sh compare；本runner不更新畫面基準

## 分組

| 組別 | 驗什麼 |
| --- | --- |
| backend | SQLite及隔離MySQL後端全套 |
| general | 一般前端工具與真瀏覽器流程, 自動納入frontend/tools的*-check.js, 下面的專用檢查除外 |
| policy | 同一個合成空間, 分別經公開註冊和邀請制後端登入／註冊 |
| inline | 寄信當下失敗仍保留待啟用帳號, 顯示正確錯誤, 不寄真信 |
| pagination | 隔離庫建立10025張卡, 驗SQL頁數／上限及畫面20筆列數 |
| seed | 產生示範資料, 驗工時圖表差異及四種帳號的操作權限 |
| realtime | 真正的Database Queue與Reverb, 驗跨帳號更新、草稿衝突、停止／恢復連線及撤權 |
| r2 | 私有測試bucket的上傳、授權下載、重播、刪除與清理 |
| runtime | 真正Nginx＋PHP-FPM正式映像, 連獨立測試庫驗登入、看板／詳細頁與並行API |

專用組別使用tools/regression-profiles.json的固定schema與埠。兩位Agent的服務、資料庫伺服器來源與前端埠分開, 不停止對方的容器。每組結束會清掉自己啟動的專用服務, 保留共享測試MySQL及合成資料, 不執行全庫清空

## R2與安全

R2只讀被Git忽略、權限0600的.local/tt291-r2.env, bucket必須是task-tracker-local。沒有這組隔離設定時明列blocked, 不回退正式R2, 不另建付費bucket。清理只限本次隨機Project的測試物件

每次執行都先查自己名下的後端, 確認APP_ENV=testing、tasktracker_test帳號與來源schema。建立的額外schema固定為tasktracker_test_開頭的已知名稱, 不載入根.env或正式Secret。測試程序逾時會清理自己的程序群組, 埠被占用時停止而不搶用

產物在artifacts/regression/<agent>/<group>/results.json與各檢查log, 不進Git。blocked、啟動失敗與測試失敗均不算通過

## GitHub

Isolated regression工作流程可從Actions手動選一組執行, 不因每次push自動加開整套測試。沿用相同runner與隔離MySQL, 不需正式Secret；R2保留本地測試設定, 不放進這個工作流程

本地通過與GitHub通過分開回報, 沒有執行的遠端工作不寫成成功。既有tag發布前的API契約及映像檢查仍保留, 不以此取代
