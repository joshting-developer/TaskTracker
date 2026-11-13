# TT-311 小待辦公開API

- Agent用PAT走公開API, 原本公開API完全沒有小待辦；網頁內部功能完整
- 公開端點沿用網頁的TodoReadService和Todos用例, 外層加Token範圍、requestId重送與稽核；工作空間層級, 不在Project底下
- 開放：列表（mine／sent、open／closed）、單筆、可交辦對象、建立交辦、修改、完成、重開、退回；不開放轉成任務、取消、通知
- 同步OpenAPI 0.11.0、開發者文件五頁、cURL和Python範例
