# TT-327 資料服務來源收斂方案

## 已確認

2026-11-14以唯讀SSH查兩台OCI, 未修改主機或雲端規則

- MySQL主機以iptables放行3306新連線, 尚未限制來源；33060只聽loopback
- Redis主機以iptables放行6379新連線, 尚未限制來源；服務仍是TLS-only
- 兩台22／SSH與Oracle InstanceServices規則需要保留, 不整包覆蓋INPUT或OUTPUT
- 此次連線快照沒有資料服務的既有TCP連線, 這不代表沒有其他合法消費者, Fly可能已自動停止
- MySQL非系統資料庫只有正式task_tracker與舊task_tracker_testing；歷史host_cache含多個來源, 不能把所有歷史IP直接當合法白名單
- Fly App目前只有對外服務的shared IPv4／IPv6, 沒有固定egress。服務入口IP不能當出口白名單
- 目前應用帳號仍有全庫GRANT, 最小權限由TT-330處理

原始規則與來源明細僅保存在忽略的.local/tt327, 不提交帳密、客戶端明細或私鑰

## 建議方案

依[Fly官方固定出口說明](https://fly.io/docs/networking/egress-ips/), nrt配置一組App-scoped固定出口, 公開價格為每IPv4每月USD3.60, IPv6隨附。需明確同意新增持續費用後才建立

| 消費者 | 允許方式 | 啟用條件 |
| --- | --- | --- |
| TaskTracker HTTP與release migration | 同一App／nrt固定出口 | 實際配置並核對來源後加入 |
| 開發與資料庫管理 | 原有SSH通道轉送到loopback | 先測通道與TLS, 不依賴浮動家用IP |
| 其他MySQL／Redis消費者 | 逐項確認其身分與固定來源 | 尚未確認, 不假定不存在 |

固定出口由App持有, 不隨Machine重新部署而刪除；新Machine套用可能有延遲, 需要驗實際來源與release階段連線。未配置或不採固定出口時, 另行設計私有通道, 不以整段Fly公網IP放行替代

## 套用與回復順序

1. 保存iptables-save、持久化設定與OCI ingress清單, 分別記錄時間與雜湊；只備份設定, 不備份／還原業務資料
2. 固定出口準備完成後, 先加精確allow規則並保留原3306／6379規則, 驗HTTP／migration連線及開發SSH通道
3. 列齊其他消費者, 才移除對應資料埠的廣泛allow, 不更動SSH、Oracle InstanceServices或其他服務
4. 安排有時限的自動回復, 保留獨立SSH工作階段；驗合法來源成功、非允許來源拒絕與TLS仍驗CA
5. Fly停止／喚醒後再核對來源與連線；完成才取消自動回復並持久化, OCI ingress同樣按資料埠與來源收斂
6. 失敗只恢復本次資料埠規則與設定, 不flush整個ruleset或共享Redis, 不修改資料／帳密

## 目前未完成

固定出口費用尚未取得同意, Redis與舊測試庫其他消費者尚未列齊, OCI雲端規則也未取得可操作的清單／權限。維持原規則, 不將唯讀盤點或既有TLS驗證當成已完成來源限制

TT-330建議新增僅限task_tracker的runtime帳號（SELECT／INSERT／UPDATE／DELETE）及獨立migration帳號（限該schema的必要DDL）, 不重設或撤銷既有管理帳號, 不影響其他服務。正式切換須先備妥Secrets、回復與實際權限拒絕驗證, 不共用全庫管理帳號
