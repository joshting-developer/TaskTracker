# TT-183 初始前端資源拆分

2026-11-12。依 Addy 主診斷＋ECC 量測、專案工程／Vue 規範執行；載入狀態沿用 UI/UX Pro Max 的明確回饋與 design-taste app-screen 的既有介面原則，不重設品牌或畫面結構。

## 同環境比較

Node 22.23.2、既有 lockfile／Vite 7.3.7，正式 build；下表為檔案 bytes 的預設 gzip，不含 HTTP headers。

| 初始資源 | 修改前 | 修改後 | 減少 |
| --- | ---: | ---: | ---: |
| JS gzip | 199,964 B | 100,720 B | 49.6% |
| CSS gzip | 110,713 B | 50,667 B | 54.2% |
| JS＋CSS gzip | 310,677 B | 151,387 B | 51.3% |
| JS 原始 minified | 609,020 B | 284,744 B | 53.2% |
| CSS 原始 minified | 694,955 B | 324,028 B | 53.4% |

固定對照及 hash 位於 `.ecc/benchmarks/frontend-bundle-2026-11-12.json`。Chrome 冷開登入頁的 Resource Timing 同樣只載一個初始 JS 與 CSS；gzip transfer 包含每檔約 300 B headers。重新整理後命中快取的 0-byte／304 不當作冷載入節省量。

MDI 四種字型產物（約 3.61 MB，含 403,216 B woff2）不再產生；不把四種格式加總說成單次網頁下載量，登入頁原本亦不一定觸發字型下載。全部功能都開過的 JS 合計從 609,020 B 增至 630,376 B：分塊／載入提示有成本，收益在按需下載，不是全站 JS 總量下降。

## 調整

- App 保留登入、導覽與共用狀態；待辦、專案列表、看板、工時分析／月曆、設定與彈窗依實際畫面條件以原生 async component 載入。ProjectTasksWorkspace 再拆看板與編輯／詳細抽屜，單看列表不載入整個看板或編輯器。
- 共用 lazyFeature 保留 Vue 對 props、events、ref／expose 的原生轉送，dirty／saving 仍由原功能擁有。150 ms 後才顯示載入文字；下載失敗提供手動重試，不自動重載頁面。重新載入需確認放棄草稿；永久失效的舊 hash 可使用此回復入口，未重試任何寫入。
- Pusher SDK 在有效看板同步啟用後才載入，等待後再次比對 generation，避免切 Project／卸載後建立舊連線。失敗仍沿用既有同步退避／輪詢，不擴大同步範圍。
- Vuetify 改用已安裝版本內建的 mdi-svg aliases；ProjectDialog 的兩個字型名稱改 `$complete`／`$plus`。移除 @mdi/font 依賴，沒有新增套件；保留現有 SVG Icon、表單元件和版面。
- Vite 明列目前使用的六個 Vuetify 元件做開發預先最佳化，避免首次開啟 lazy 表單觸發 dev dependency reload。沒有提高 chunk 警告門檻掩蓋大檔案，build 不再出現 >500 KB 警告。

## 資料存取與風險

本卡不新增或改版 API，不把路由切分變成預抓整個 Workspace；既有 SQL 分頁／按需詳細與授權範圍維持。純程式模組 Promise 不保存 API payload／actor，卸載與跨 Workspace 資料取消規則仍由原功能處理。首次進入新功能會多一次模組下載；慢網路及部署刪除舊 hash 均可能失敗，因此提供可見回復與保留導覽。

未宣稱 RUM、LCP／INP p95 或正式遠端部署改善百分比；這是同機 build＋隔離 HTTP／Chrome 的 bytes 與操作證據。其他 API／輪詢／查詢卡不併入結案。

## 驗證

- `npm --prefix frontend run build`、`test:api`、`test:composables` 通過。
- 正式建置版 `test:bundle`：登入不下載工時分析／看板／Markdown／Pusher；進首頁才取得 Markdown，實際預覽 checklist 並儲存待辦；看板列表不下載看板畫面／Pusher；Vuetify 登入錯誤圖示與新增專案圖示均為 SVG，無字型請求。
- 同一腳本實際切離線並阻斷 TimeAnalysis chunk，顯示失敗／停止背景重試、取消頁面重載保留畫面，恢復網路後手動重試成功；桌機／390px 無橫向溢位。截圖與 Network 原始資料在忽略的 `frontend/artifacts/bundle/`。
- 正式建置版 `test:board`：三尺寸、底部卡片、Epic、偏好、密度、拖曳／雙擊／鍵盤／手機編輯通過。
- 完整 `test:e2e`（dev，真實隔離 MySQL 13029）通過：協作／邀請／角色／關聯／工時／通知／狀態／看板、完整詳細頁、草稿衝突、Project ACL／撤權、Todo 轉卡及手機。舊測試的成員選單改為操作現有遠端候選搜尋、精確 label，未放寬原有權限／版本斷言。此腳本使用 `/src/api` fixture helper，因此不假稱它是 production preview。
- 正式建置版 `test:realtime`：獨立 tasktracker-realtime-test＋MySQL test schema／Redis／Queue／Reverb，雙帳號更新與無內容訊號、草稿原版本／409、實際停止及重啟 Reverb 補抓、撤權斷線／拒絕重訂閱／CSRF及三尺寸通過。同步測試改為符合既有草稿期間只重驗 Project 的契約，不要求偷換列表或表單版本。測試專用容器／三個 volumes 已清理，主服務資料未重設。

重跑 bundle：`E2E_API_URL=http://127.0.0.1:13029 PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' npm --prefix frontend run test:bundle`；必須使用獨立測試服務，不指向業務庫。
