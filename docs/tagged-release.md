# Tag 發布

main push 執行契約 CI, 不直接部署。發布由 `v<major>.<minor>.<patch>` tag 觸發, 新 tag 必須指向當時 main HEAD；既有版本可透過 workflow_dispatch 指定原 tag 續跑, 不移動 tag

1. 先執行來源秘密掃描與契約測試, 再建置固定來源的 linux/amd64 映像, 用隔離合成環境冒煙驗證
2. 匯出受測映像, 經短期 Actions artifact 傳給部署 job, 核對 archive SHA256、image ID、原始碼 SHA 與版本
3. 部署 job 從 production Environment 的 FLY_CONFIG_TOML 建立私有暫存設定, 從該設定取得 App 與 HTTPS origin, 不在腳本寫死正式目標
4. 只讀已套用的 migration 名稱並拒絕未核准的 pending schema, 驗證私有設定後推送固定版本至該 App 的 registry
5. 使用同一映像在一次性、無 HTTP service 的 Machine 執行 migration, 帳密只走 SSH stdin, 完成或失敗都清理自己的 Machine；成功才部署 HTTP
6. 部署後驗 health 與公開註冊政策, 清除暫存設定與原始工具輸出。摘要只留下版本和原始碼 SHA, 不含正式 App、主機或帳號

部署 job 使用 [Fly 部署說明](fly-deployment.md)列出的 Secrets, 不把它們傳入 build／test job、映像或公開 artifact。發布由 concurrency 序列化, 任一步失敗就停止, 不自動 seed／reset

已發布的版本標籤只有在拉回的 image ID 與受驗證映像完全相同時才能重用；不同映像、registry 權限錯誤或 migration 失敗都停止。重跑沿用原受測 artifact, 不另建不同內容的同版本映像

本機單元測試與模擬部署可驗編排和拒絕條件, 真正 tag 流程仍須另做 GitHub Actions 與正式站驗收。歷次個人部署紀錄不隨產品 repository 分發
