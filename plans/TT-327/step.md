# 執行紀錄

- 已讀現行規範、共用skill及TT-159交辦全文／留言／交接
- TT-327預估90分鐘, 先查來源與出口, 不執行未知影響的封鎖
- TT-330仍是Josh承辦的deferred卡, 不直接搶領, 先準備可檢閱的權限分離方案

- 兩台OCI的iptables與監聽埠唯讀盤點完成, SSH成功, 不變更任何規則
- Fly目前無固定egress, 服務入口IP不能當出口；官方固定出口USD3.60／月, 尚未分配
- MySQL確有全庫及GRANT權限, 其他非系統DB僅舊測試庫；不能只憑無活動連線判斷無其他Redis消費者
- 已整理可檢閱allowlist／保留SSH及自動回復順序, 見docs/oci-access-hardening-tt327.md
- 尚待付費出口決定、其他合法消費者與OCI規則清單, 保留現有防火牆
