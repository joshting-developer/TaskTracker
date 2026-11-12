# TT-264 小待辦控制項排版修復

## 重現與根因

Bug看板共2張：TT-264未完成、TT-258狀態篩選已由其他承辦者結案。此次只領TT-264，不重開既有完成卡。

原截圖的高方塊在本次版本未重現；真實隔離登入量到同一排版缺陷的換行表現：PagedSelect根節點含標籤、控制項與搜尋／錯誤，佔滿flex列後送出被擠到下一列。1440px初始選單y=405.75、按鈕y=472.75；展開搜尋按鈕移至557.25，錯誤時584.05。控制項41px且搜尋觸控範圍較小。不能把原截圖高度當本輪實測值。

## 限定修正

只修改TodoHome.vue的scoped CSS，沒有改API、Markdown、PagedSelect正文、候選查詢、寫入或授權：

- Grid第一欄minmax(0,1fr)，選單標籤／控制項明確行高與間距；送出對齊控制項列，按鈕不隨整個選項容器stretch。
- 選單、搜尋及送出皆44px；搜尋保留原按鈕與aria-expanded／鍵盤語意。
- 小於480px明確單欄，送出滿寬分行，搜尋展開／載入／錯誤在其自然欄位內顯示。
- 原生select相容分支也保留，不為CSS新增狀態或重設共用表單。

## Skill審查

實際載入共用工程／verification、Ponytail lite、Vue與其四個核心reference、UI UX Pro Max、Design Taste app-screen。查UX觸控／表單配置建議，優先既有元件、限定scope；不採品牌改色、字型替換、表單重設計或新套件。風險為長選項／搜尋展開影響對齊及手機寬度，透過幾何斷言與真實UI驗證處理。

## 實測

重跑專用檢查（從frontend工作目錄執行）：

```sh
cd frontend
E2E_API_URL=http://127.0.0.1:13079 \
PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' \
node tools/todo-quick-layout-check.js
```

先啟動本次獨立tasktracker-codex-visual／docker-compose.mysql-test.yml，不可接主庫。E2E_BEFORE=1僅量修前現象，不放寬修後驗收。

- 修後21組＝3尺寸×空／有內容／搜尋展開／交辦他人／載入／錯誤／送出中。每組select／search／submit均44px；1440、768的y完全相同，390明確分行，頁面無橫向溢出。
- 兩個真實註冊帳號、邀請加入、自己Markdown速記／交辦他人、鍵盤Enter、忙碌disabled、重複click只一次request、私人待辦隔離均通過。載入只延遲真HTTP、錯誤只中斷網路，沒有業務mock DTO。
- 既有完整browser-check通過，包含狀態多選（TT-258回歸）、跨租戶／ACL、草稿／版本衝突、Markdown／轉卡／工時／手機；build、composables、API client與Skill integrity通過。
- 48張候選對原核准基準逐張比對：42張PASS，6張預期差異為03-home-todos與16-denied各3尺寸；後者本來就安全退回首頁，故同樣呈現小待辦。比較退出碼1屬預期變更證據，不當作正式visual PASS。
- 原核准圖片與比較器／policy參數未改。新候選manifest `f95f27eca6bc0a4e5c6c876c4d138ccd048a8c5d9d3387c16e0e95a45e40f118`，6張修復圖需明確核准後才更新基準並正式比較。

原始截圖／量測／完整日誌在忽略artifacts/tt264；候選與核准bundle在artifacts/visual，圖片及合成帳號不進Git。本輪有其他人正在修改後端Project時間戳，保留差異且不加入本卡提交，不將其成果歸本卡。

## 核准及收尾

使用者已明確回覆「核准這6張修復畫面」。只替換03-home-todos／16-denied各三尺寸，42張其他圖保留原核准PNG bytes；原完整bundle封存在忽略的artifacts/visual/approved-history/darwin-arm64-before-TT-264，沒有提高比較容忍或修改harness。

新核准manifest為`92dd552cde4a791b38184f3510e18b4a9e68d58cede5421cbd22d3491051eeab`，記錄原manifest及被核准的候選digest／6張清單。正式三尺寸compare結果及結案於實測後回填，不把預期差異報告當作正式通過。
