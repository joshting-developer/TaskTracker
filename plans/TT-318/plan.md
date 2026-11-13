# TT-318 Symfony安全更新

- 預估45分鐘, 先盤點目前lock／官方公告與實際runtime, 再只更新必要修補版本
- 保留Laravel 13與目前Docker PHP, 釐清composer PHP宣告與真正最低版本
- 用既有Docker Composer工具鏈, 不執行安裝script或對現用DB初始化；新vendor使用獨立隔離映像驗證
- 共用工程／Laravel架構／ECC／Ponytail lite；安全公告不忽略, 原生MySQL與memory／公開API／附件／路由依影響回歸, 如實記錄限制
