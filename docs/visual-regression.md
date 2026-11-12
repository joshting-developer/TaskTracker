# 視覺回歸與基準核准

TT-270／2026-11-13。沿用Playwright、真實隔離Laravel／MySQL與既有navigation／board-actions；產品Vue、CSS及業務規則沒有重設計。

## 本地一鍵流程

```sh
# macOS可沿用已安裝Chrome；版本不同會明確拒絕當作同一基準。
export PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
sh tools/visual-check.sh candidate
sh tools/visual-check.sh prove
sh tools/visual-check.sh compare
```

每次只重建／清理tasktracker-codex-visual、13079、tasktracker_test與本次私有測試volumes；不繼承主服務.env／資料卷。Compare缺核准在啟動Docker前失敗。亦可用frontend的test:visual、test:visual:candidate、test:visual:prove。必要時首次建既有backend image；正常沿用現有image。

@playwright/test固定1.63.0，和現有playwright同版，只新增一個開發套件，共用引擎；不引用其私有PNG比較器或自己寫解碼器。[官方截圖斷言](https://playwright.dev/docs/test-snapshots)會等待穩定畫面並提供expected／actual／diff；一般playwright單獨沒有此斷言，因此沒有假定舊runner已具備snapshot測試。

## 固定條件與真實情境

48張候選＝16情境×桌面1440×900、平板768×1024、手機390×844，DPR1／zh-TW／Asia/Taipei／light／reduced-motion／固定2026-11-13工作日、鎖定套件及實際瀏覽器版本。等待font ready並禁用截圖動畫、caret；停用GPU減少合成器差異。字型仍依實際OS，不能用macOS baseline宣稱Linux CI視覺通過，環境不同會失敗。

情境：登入、原生表單驗證、首頁Markdown待辦、專案列表、任務列表、篩選展開、詳細頁、編輯抽屜、 populated看板、拖曳後、空看板、載入中、網路錯誤、工作區選擇、一般成員無專案、拒絕存取後的安全退回與通知。實測modal初始焦點／反向Tab／Escape、頁面無橫向溢出、真實拖曳保存與權限隱藏。

Fixture由真實HTTP註冊／建專案／建卡／速記／邀請既有帳號，再用受限CLI固定合成專案排序時間。不替換業務回應。載入情境只延遲真實請求後放行；錯誤情境中斷網路，走真實產品錯誤處理。沒有虛構API DTO、產品debug HTTP端點或真實帳號資料。

backend/tools/visual-fixture-clock.php只允許testing＋TASKTRACKER_MYSQL_TEST=1、mysql／tasktracker_test資料庫與帳號；再核對visual-* @example.test、指定Workspace／Project及固定代碼，只改兩筆合成專案時間，不作任意SQL入口。production／local／未啟用旗標在bootstrap前拒絕，另有3項Unit案例。這是明確測試工具，不將一般Service直接查Model作例外。

## 候選、比較與人工核准

候選：artifacts/visual/candidate/index.html；manifest綁48張PNG hash、環境與本次來源指紋。圖片／完整報告均忽略，不進Git。候選產生成功不是視覺驗收通過。

人工確實檢閱並同意後，才執行：

```sh
python3 tools/visual-baseline.py approve --digest <檢閱的manifest SHA256> --reviewer <核准者> --note <核准來源與範圍>
# CI候選另加 --artifact-run-id <已檢閱的run ID>
sh tools/visual-check.sh compare
```

核准只複製該digest的合成候選至ignored approved-<platform>-<arch>，Git僅記docs/visual-baselines/<platform>-<arch>.json的核准者／範圍／manifest與環境。已存在approved不自動覆蓋；未獲UI變更核准不能重新洗baseline或提高容忍度。核准前的來源或候選hash改變由approve拒絕；PNG或harness被改由preflight拒絕。比較允許產品來源改動，但結果必須綁定本輪sourceFiles；compare禁止update-snapshots並在前後再驗baseline hash。

候選校準曾量到文字／backdrop邊緣3～58個通道值差異像素、最大單通道色階差2，並無位移。候選設定採Playwright perceptual threshold 0.02、maxDiffPixels 0；只容許此類細微抗鋸齒色差，任何超過色差門檻的像素都失敗，不以全圖百分比忽略差異。此設定須和實際候選一起人工核准，不宣稱數值本身保證所有可用性問題。互動／溢出／權限另由行為斷言保護。

## 退化校準與CI

prove只對未核准候選作「校準」，不寫任何人工核准。校準只跑desktop的05-issues負例（完整三尺寸另由正式compare驗）；原樣→注入spacing／overflow／hide-action→還原：必須原樣及還原成功，三個負例不只退出失敗，還必須在05-issues留下真實diff PNG，避免將其他測試失敗誤報成阻擋成功。CSS僅注入測試頁並在finally移除，產品來源無改動。

本地輸出comparison／calibration-*的before（expected）／after（actual）／diff、command.log、JSON報告及summary.json；scope明確區分calibration-only和approved比較。

GitHub workflow visual-regression.yml為PR及手動候選／校準／比較門檻：Linux runner產合成候選與退化證據，下載明確run ID的已檢閱artifact，必須匹配Git內approval digest；缺核准／artifact或OS／瀏覽器不符會失敗並上傳候選供review，不會自動核准。未push／未跑遠端job，不宣稱GitHub Actions綠燈；各平台獨立核准檔可並存；approve請在與候選相同的平台環境執行。現用核准為darwin-arm64；Linux尚無首次artifact／核准，因此目前遠端workflow預期會因缺基準而失敗，正式設Required Check前必須完成部署看板的Linux接線驗收，不把本機macOS圖拿去證明Linux。

本輪實測結果、核准與未驗項以docs/structure-optimization-acceptance-2026-11-13.md及卡片為準。

## 獨立審查後的防降級補強

comparison參數來自唯一policy.json，版本讀實際安裝套件，不用自述常數冒充。核准紀錄另外綁harness SHA256（config／情境／policy／CLI／PHP fixture），compare前後都驗；改容忍度或刪情境會立即拒絕。原48張已核准PNG manifest digest保持不變，這是技術鎖的補強，不新增UI核准。未來更改harness必須明確review／更新技術綁定，不能直接改成PASS。

每次命令先清除舊summary狀態；candidate／prove先把proof設pending，失敗不沿用上一輪PASS。Proof綁candidate digest／source／harness／環境，正式summary附sourceFiles／active approvalFile；共用check_evidence會比對當下檔案、核准與harness，避免新來源搭配舊畫面結果。每個固定結果目錄執行前清空，舊diff不作新失敗證據。

Atomic lock拒絕同時啟動或stale lock，不拆掉另一個runner；trap保留退出碼並清本次服務／volume／lock。CI觸發涵蓋前端、後端app／routes／config、fixture、Compose及核准檔，並跑工具與fixture邊界測試。未經push仍不能宣稱遠端已實跑。

後續審查補上實際匯入的navigation／board-actions helpers至harness與source；shared gate會重新列舉sourceRoots，新增來源也使舊結果失效。實際測helper異動／新增source均被擋，還原後門檻通過。
