# Issue體驗／附件路線圖：現況與卡片對照

2026-11-13，TT-286。來源：[需求文件](issue-experience-and-attachments-roadmap-2026-11-13.md)。本輪只做源碼／正式規格與卡片對照、補卡和分類，沒有產品實作、雲端操作或新功能驗收。來源文件開頭「僅新增文件、不建卡」是該文件產生當次的交付註記；本輪依使用者直接要求查重建卡，不執行文件內「開始實作」的未授權動作。

## 現況確認

- 共用Markdown編輯／預覽已存在：`MarkdownEditor.vue`、`MarkdownContent.vue`、`ui/markdown.js`；描述及留言沿用它。**沒有**圖片paste/drop/file入口，image renderer只輸出安全連結，DOMPurify沒有允許img。這是已確認的舊安全策略，不是上傳功能已存在。
- 目前app／migrations／routes沒有附件model／metadata表／upload／authorized-content資源；正式OpenAPI沒有附件operation。不能因Issue公開CRUD已交付就宣稱PAT可傳圖片。
- `config/filesystems.php`已有Laravel通用private local／s3配置骨架，**不是附件ACL／pending生命週期或R2已接線**。composer.lock實際packages沒有`league/flysystem-aws-s3-v3`，僅框架suggest／require-dev字樣不能當安裝成功。未連雲端或索取Secret。
- IssuePanel／IssueDetail已有dirty、saving、離開保護、revision衝突、詳細頁屬性／關聯／活動，以及列表／看板／密度／拖曳。這些沿用，待做的是含圖片後的體驗與視覺精修，沒有重開「建立詳細頁」或「建立Markdown」歷史卡。
- 三尺寸與核准baseline機制已由TT-270／271交付；Linux正式CI啟用仍是TT-273，不另開同一張驗證基建卡。後續UI卡需實際行為及人工核准有意差異，不以本輪對照視為已驗收。

## 查重與更新既有卡

| 卡片 | 本輪處理 | 主要看板 |
| --- | --- | --- |
| TT-191 | 沿用附件後端基建，合併來源包1＋2；補metadata／ACL／內部API／Storage／補償／TTL／有界讀取，優先提升P0。 | 實際功能 |
| TT-282 | 沿用貼圖卡，提升P0，收斂為**Issue建卡描述**的paste／drop／file與pending草稿；留言／Todo移第二階段，安全閱讀另TT-287。 | 實際功能 |
| TT-281 | 沿用列表快速編輯入口，不和整體密度精修TT-290重疊。 | 實際功能 |

原估時360／360／180分鐘保留，未因補文件直接覆寫；實作者領取時按最新範圍說明重估。修改前讀回open未指派、以最新revision寫入，原描述／來源沿革保留並加「新路線圖優先」段落；不是把他人正在做的卡改方向。

**兩個更新決策已明確註記取代舊文字**：

1. 正式預定供應商是R2，先用local private disk／fake完整開發驗收。舊「R2只是候選」不再當未來選商結論，但不代表已部署。
2. MVP先Issue描述；共用編輯器要顯式啟用圖片，留言第二階段、Todo可先禁用。舊「描述／留言／Todo一起開」不再當MVP要求。

## 新增卡片

P0映射API的urgent，P1映射high，P2映射low；階段優先不是Bug嚴重度。所有新功能卡保持open未指派，有正式預估分鐘、現況／查重、來源章節、依賴與驗收；不是已承諾立即實作P2。

| 卡片 | 工作包 | 優先／估時 | 主要看板 |
| --- | --- | --- | --- |
| TT-287 | 私有附件Markdown安全顯示、授權圖片與放大 | P0／240分 | 實際功能 |
| TT-288 | 建立／編輯表單主次、工具列及儲存回饋 | P1／240分 | 實際功能 |
| TT-289 | 詳細頁閱讀、附件與活動層次 | P1／240分 | 實際功能 |
| TT-290 | 列表／看板密度、長標題及操作狀態 | P1／300分 | 實際功能 |
| TT-291 | R2配置與隔離Bucket實際驗收 | P1／180分；等待使用者之後提供資訊 | 部署 |
| TT-292 | 已存在local附件時的R2無損遷移／回退 | P2／240分；條件式，現在無附件可搬 | 部署 |
| TT-293 | 第二階段Issue留言圖片 | P2／180分 | 實際功能 |
| TT-294 | 第二階段Todo隱私與轉卡圖片 | P2／240分 | 實際功能 |
| TT-295 | 第二階段PDF／ZIP等一般附件 | P2／240分 | 實際功能 |
| TT-296 | 公開PAT附件API／Scope／文件與獨立客戶端 | P2／300分 | AI整合/API |

本輪規劃追蹤TT-286歸實際功能，是產品路線圖拆解，不是重構或效能改造。新增內部HTTP路由沒有因此歸AI整合/API；只有TT-296真對外公開契約歸該板。

## 依賴與避免範圍重疊

- **本地MVP**：TT-191（合併metadata＋ACL＋內部API＋local Storage）→ TT-282上傳草稿＋TT-287可信顯示平行接線 → 真PNG剪貼簿／JPEG拖曳／WebP手機選檔、建卡、重載、放大與撤權完整旅程。
- TT-282不再另造圖片resolver；TT-287不再造上傳與生命週期。TT-288／289接已完成能力做流程精修，不再建立新的附件backend。TT-290保留原server pagination／Epic泳道／漸進掛載，和TT-281共用action風格。
- TT-291等待外部設定只影響雲端驗收，不阻塞前三張本地MVP。TT-292獨立驗無損遷移，不把換disk設定當資料搬遷；未有真實local附件時不自行製造主庫遷移。
- TT-293／294／295／296皆非MVP阻擋。Todo沒有Project時須另定私人參與者scope，不能把共用編輯器開關打開就洩漏。PAT另驗read/write scope交集，不能偷用Session內部路由。
- R2直傳／multipart、受保護CDN、縮圖／轉碼及換Rich Text Editor，來源列為「先量測／僅候選」。本輪保留研究選項，**不額外開成已排定的產品實作卡**；需有流量／容量／UX證據或後續選型決策再拆，以免把選項全部變成無限待辦。
- 10MiB／20張、GIF動畫、signed URL／後端代理等仍是文件建議或需設計確認；卡片要求設定一致與安全驗證，不冒稱使用者已確認這些細節。

## 驗證與交付

已核對現有全部卡片含closed／deferred、讀相關卡全文、掃附件相關model／migration／route／renderer及lockfile／正式OpenAPI。更新與收錄均以目前revision／requestId操作；保留其他收錄，不改其他Agent承辦與狀態。精確清單見[機器對照](planning/issue-experience-roadmap-2026-11-13.json)。

本輪只有文件／卡片操作，驗證JSON／連結／卡片讀回／Skill來源完整性；未跑產品build、MySQL壓測、UI瀏覽器或R2測試，因本輪未實作那些變更。只本機提交，不push，不碰現用DB seed／reset或共享Redis。

本輪追蹤TT-286已填預估45分鐘／本人正式實際工時5分鐘並讀回；實際自建立追蹤卡起按分鐘估計，未補領卡前探索。只有本規劃卡結案，所有產品改善卡保持未開始。
