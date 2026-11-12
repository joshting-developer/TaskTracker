# 結構優化驗收

2026-11-13。範圍為結構優化看板TT-266～271；共用Skill／Memory整理與前端驗證門檻，不重設計產品或改業務API。

## 交付與來源

- 12份原Skill搬至Side Project分類庫，加既有任務操作及固定Ponytail共14份；358原vendor檔hash一致、379公開canonical檔及入口／owned links校驗通過。相同LICENSE合法保留，並非重複Skill。
- TaskTracker只保留symlink與短入口；自有工程與Laravel規範泛化，產品設定留工程綁定／Memory。AGENTS保留規範，不累加歷史。
- registry唯一矩陣、框架偵測與加法tags；8個新host session實測選用與工具讀取，對應[證據](evidence/skill-host-selection-2026-11-13.json)。缺檢查、失敗、過期來源及未核准visual不得PASS。
- 使用者追加的「每次前端新增修改都需Skill審查驗證」及執行頻率已由共用baseline／verification統一維護，frontend/AGENTS只作入口。按提交階段驗受影響流程，共用變更擴大，大階段再跑完整回歸。

共用庫階段commit：53bb5a4搬移、43c0382矩陣、be12b56前端頻率與證據綁定、1406d35完整來源集合檢查；TaskTracker前段為e1e0f82／b480832／40c3453／b8bffc3。各repo保留原歷史，未push。

## 視覺與行為實測

用真實隔離MySQL／Laravel＋既有Chrome；16情境×三尺寸共48張。使用者本輪已核准影像及微小抗鋸齒設定，immutable manifest：`fcf9ee11af9987abe9a5dc70ba1e4827b78872cf7763091c8194f169275ca491`。正式compare 3／3通過，環境吻合，沒有改寫approved。

- 載入、網路錯誤、無專案／拒絕存取、角色差異、modal焦點／Escape、頁面溢出、拖曳保存均經實際流程。
- 校準只跑desktop／05-issues：原樣exit0，spacing／overflow／hide-action各exit1且有真實diff，還原exit0。不用其他測試錯誤冒充回歸偵測成功。
- 額外修改測試容忍度或刪掉shot呼叫，harness preflight均拒絕，還原通過。保留完整參數／情境／CLI／fixture的技術綁定，核准的48圖與參數不變。
- 完整既有browser-check通過，包含兩帳號、ACL、撤權／草稿、衝突、revision、關聯、工時、待辦轉卡與手機。前端build、composables、API client檢查通過。
- 後端既有memory 155項／7855 assertions通過；新增fixture拒絕3項／6 assertions通過。新工具Python 4項，共用工具2＋選用4＋證據3項均通過，限定Pint、Python／JS／shell語法及diff檢查通過。

[機器摘要](evidence/visual-regression-2026-11-13.json)保留本輪harness／source／manifest綁定、校準及tamper probe；完整sourceFiles、expected／actual／diff／logs及原始host trace留忽略artifacts。圖片／秘密不入Git。

## 獨立審查與修正

第一次獨立唯讀審查發現：P1容忍度／情境未綁定；P2舊PASS來源不明、CI觸發缺口、單平台核准及報告尚未建立。均已處理：

1. 一份policy，讀實際Playwright版本，approval綁完整harness，前後重驗；篡改兩種情況已實測拒絕。
2. 執行前使舊summary／proof失效；proof與正式summary綁source／harness／manifest，shared gate驗當前核准與每筆來源hash。清除本輪舊diff，避免誤用舊圖。
3. CI觸發補齊app／routes／config、fixture、Compose、核准；工具與guard測試進同一隔離流程。
4. 核准按平台分檔、bundle分profile，Darwin／Linux不互相覆蓋。Atomic lock防兩個runner拆掉彼此測試環境。
5. 本文件及操作文件已建立，來源／限制與實測分開。

第二輪獨立Codex唯讀複核實際載入指定四份canonical Skill，指出兩項P2：直接匯入的navigation／board helper未綁定、新增來源集合未驗。兩者已補，並實際改helper／新增frontend/src檔案驗拒絕，還原後完整shared gate通過。最後重跑全新拓樸的校準與正式三尺寸比較皆通過。[獨立審查證據](evidence/structure-independent-review-2026-11-13.json)記實際讀取及trace hash；中途Claude額度中斷的follow-up不算通過。審查者只讀檔，測試是本輪工具實際執行，不混稱彼此做過的事。

依證據修正後本地交付無待處理P1／P2；本輪仍不宣稱全產品無任何Bug或遠端已驗。

## 實際界線

本輪未修改frontend/src、backend/app或routes產品原始碼，不新增migration或產品debug HTTP。fixture CLI只在testing／tasktracker_test且指定合成人員／Workspace／Project可執行。所有本次容器、volumes與lock依trap清理，不碰主庫、全域hook或其他專案。

本機核准為darwin-arm64／Chrome157.0.8103.52／Playwright1.63.0，不能取代Linux。尚未授權push，未執行遠端GitHub job；Linux首批artifact／核准及Required Check啟用已另建**TT-273，部署看板、open未指派**。目前Linux workflow會因缺核准基準明確失敗，不能宣稱遠端綠燈或直接設Required Check。這是後續部署啟用範圍，不假裝本地圖已跨平台驗收。

視覺校準觀測到新專案SQL時間戳為NULL、排序退回UUID，已另建**TT-274，實際功能看板、Bug／open未指派**；只讀確認隔離庫，未查或修主庫。此產品資料問題與結構門檻分開，受限fixture只是穩定合成資料，不冒充修正。

## 最後對照清單（本地交付）

- [x] 單一canonical、合法LICENSE／references及所有consumer入口完整。
- [x] 官方固定Ponytail／lite且無全域plugin／hook，安全／分層／PHPDoc優先。
- [x] Codex／Claude新session實際讀取與四類任務8／8，非只列名字。
- [x] 唯一矩陣＋失敗／缺失／過期來源／未核准視覺證據拒絕。
- [x] 三尺寸48圖有使用者明確核准，正式比較與環境綁定通過。
- [x] 三種退化有真實diff並FAIL、還原PASS；四種harness竄改及新增來源皆拒絕。
- [x] 既有完整前端行為、後端隔離／權限／revision及新增fixture安全測試未退化。
- [x] 原repo階段提交、持續回填、秘密與圖片不入Git，測試拓樸清理，沒有push或主庫修改。

TT-270實作commit cd05f47；TT-271本階段交付包含前端短入口、共用規範分工與這份驗收清單。遠端Linux啟用TT-273與產品排序Bug TT-274如前節明確分責，不冒充已實作。
