# 看板即時同步

更新：2026-11-05（Asia/Taipei）

使用者要求看板加入WebSocket、調整Laravel及Docker，完成後重啟整組Compose。以下是這次實作選擇，不擴張成全站聊天、線上成員或共同編輯功能。

## 更新流程

1. Laravel原有API在交易內檢查Session、permission、Workspace／Project、revision及requestId，完成業務寫入。
2. `ProjectSignals`於commit後派送`ProjectChanged`，Redis Queue worker透過Reverb廣播；rollback與requestId重放不派送。
3. 看板以`pusher-js`訂閱目前Project私有頻道，收到訊號後合併短時間事件、重新呼叫原有API，伺服器重新判斷資料可見範圍。
4. 本機修改先沿用既有API回應；其他使用者的畫面收到事件後更新。Default與自訂看板共用Project頻道，套各自收錄與搜尋條件。

Issue新增／編輯／狀態／歸屬、留言、工時、專案／狀態設定、看板收錄／移除、Project ACL及待辦轉卡均走既有受保護寫入後通知。帳號名稱／Workspace設定及角色異動亦通知該空間Project重讀。小待辦站內通知本身仍使用原有輪詢，沒有本輪新增的個人頻道。

## HTTP及事件契約

| 入口 | 輸入／回傳 | 邊界 |
| --- | --- | --- |
| `POST /api/realtime/config` | `{}` → `{ok:true,data:{enabled,key,path:'/reverb'}}` | 需登入；key為公開app key，停用時null |
| `POST /api/realtime/authorize` | `{channel_name,socket_id}` → `{ok:true,data:{auth}}` | Session／CSRF／可信origin／120次每分鐘節流；交易內重查project.read及issue.read |
| 私有頻道 | `private-workspaces.<workspaceId>.projects.<projectId>` | ID來自可信API；URL或前端欄位不能代替權限 |
| 事件 | `project.changed`，資料固定`{changed:true}` | 不含標題、任務／成員內容、角色或個人資訊 |

authorize只簽固定格式的private頻道與數字socket ID，透過Laravel broadcaster產生Pusher簽章；不啟用框架預設排除CSRF的broadcasting/auth路由。無Project資格沿用不洩漏資源的404，跨Workspace為403，未登入401、無CSRF419。API回應不快取，不回傳secret或容器內部網址。Reverb禁止client events，瀏覽器不能自行廣播業務訊號。

權限撤銷不會強制踢掉Reverb既有socket；舊訂閱可能短暫看到無內容的更新訊號，接著API重讀會清除該Project與連線。下次訂閱必須重新授權。這不是「撤權後連活動訊號都完全不外洩」的正式強制斷線方案。

## 前端行為與限制

- 只有看板頁訂閱；切專案、離開看板、登出或撤權會disconnect。抽屜編輯期間仍更新背景卡片；完整詳細頁不新增訂閱。
- 訂閱成功／重新連線後補讀API，避免斷線期間漏更新；收到事件以200ms合併刷新，避免多個刷新彼此覆蓋。
- 原30秒輪詢保留。廣播未啟用、網路或Reverb故障仍可透過API操作，畫面顯示「定期更新」或「連線中…」，連上後「即時同步」。首次設定／授權失敗每15秒重試，連線自身使用Pusher重連機制。
- 不重設未保存表單及其基準revision。其他人改了同張卡，自己的草稿仍在，儲存依原有409衝突流程處理。
- 非保證送達通知：沒有新增durable outbox。commit後Redis派送失敗只記受控log，不把已成功交易改成API失敗；Queue重試三次，訊號仍可能遺失，由重連補讀／輪詢收斂。
- 每個更新仍讀取原Workspace API，沒有新增增量同步、伺服器分頁或大量連線容量保證。正式SaaS需另作負載與撤權策略設計。

## Docker與本機啟動

Compose明確設定`BROADCAST_CONNECTION=reverb`，API產生事件、queue消費、websocket服務執行Reverb，Redis處理佇列。Queue及frontend等Reverb健康後啟動。

瀏覽器連相同origin的`/reverb/app/<公開key>`，Vite的`/reverb` WebSocket proxy移除前綴後轉到`websocket:8080`。開發與preview皆支援；瀏覽器不需要容器hostname或secret。正式反向代理也需保留這個rewrite／Upgrade語意，HTTPS頁面使用WSS。本機接口仍只綁loopback，金鑰仍從既有資料volume讀取。

```sh
docker compose up -d --build
# 類別變更需重啟長駐程序；整組重啟不移除volume：
docker compose down
docker compose up -d
```

不使用`down -v`重設業務資料。本輪不新增schema或重新Seed。

## 驗證入口

PHP：`docker compose exec -T backend php artisan test --do-not-cache-result`。`RealtimeTest`覆蓋簽章、公鑰設定、租戶／Project ACL／撤權、origin、commit／rollback、重試及revision衝突。

真實瀏覽器測試使用獨立Compose專案及全新volume，絕不指向主服務。從專案根目錄啟動：

```sh
TASKTRACKER_API_PORT=13024 TASKTRACKER_WS_PORT=18080 TASKTRACKER_PORT=5175 \
  docker compose -p tasktracker-realtime-test up -d backend queue websocket redis
cd frontend
npm run build
E2E_API_URL=http://127.0.0.1:13024 E2E_REALTIME_PROJECT=tasktracker-realtime-test \
  REVERB_PROXY_TARGET=http://127.0.0.1:18080 npm run test:realtime
```

可用`PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH`指定現有Chromium。測試會暫停／重啟**上述獨立專案**的websocket；退出時恢復服務。驗證兩個帳號的卡片同步、無內容payload、草稿與409、斷線後補抓、撤權清除／斷線、真實HTTP CSRF及1440／768／390三尺寸。截圖於忽略的`frontend/artifacts/realtime/`。

## 參考

查核日期2026-11-05。交易後派送與私有頻道採[Laravel 13 Broadcasting官方文件](https://laravel.com/framework/docs/13.x/broadcasting)；Reverb設定、長駐服務與代理參考[Laravel Reverb官方文件](https://laravel.com/framework/docs/13.x/reverb)。實際簽章／client events選項另外核對本專案lockfile安裝的Laravel／Reverb原始碼。
