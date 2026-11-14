# TT-321 進度

- 已看本人紙條（無待辦）並領卡, 沿用既有預估120分鐘
- Fly舊帳號已由Josh核准切到joshting.1999@gmail.com；舊版CLI只回personal別名, 正在以新版核對josh-90真實組織識別
- TT-320尚未發布版本映像, TT-323仍是空庫部署, 目前沒有正式App或主庫搬移
- 新CLI已登入正確帳號, orgs show顯示本人personal組織, 無既有App；後續TT-323實際建App發現josh-90不存在, 不以此認定組織相同
- 完成docker/production/runtime.env.example與docs/production-environment.md, 分離一般設定／Secrets, 沿用實際r2-attachments名稱、正式TLS、host-only secure Session及production Redis prefix
- 正式映像補後端resources與公開頁素材allowlist；entrypoint支援一次性命令, 隔離migration執行後正常退出
- 新映像隔離MySQL／專用Redis啟動通過, health／首頁／login／SPA／OpenAPI／docs／secure Cookie及設定斷言通過；沒有接正式庫或寄真信
- 真Chrome登入頁1440／768／390px均無橫向溢位或pageerror, 關閉公開註冊；保留現有設計, 不更動元件
- 已建立TT-342追蹤使用者新增的共用Fly部署Skill要求, 暫open未指派, 本卡交付後依序處理
