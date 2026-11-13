# TT-318進度

- 已讀交辦、原卡與現有Composer設定, 預估45分鐘並領取；先查7則安全公告與PHP契約
- 官方7則公告已逐項盤點, 最小更新僅http-foundation／http-kernel／routing至8.1.11、mime至8.1.10, Laravel及Docker PHP不變；Composer PHP下限對齊既有8.4.1需求
- 新增4個HTTP／MIME安全行為回歸, 舊vendor4項都失敗；新vendor以正確RfcComplianceException型別4項通過。新獨立映像已建置, audit空清單、platform／Composer validate通過
- 正在跑新映像完整memory／原生MySQL, 尚未宣稱正式Fly已發布或其他安全卡通過
- 第一輪完整389項僅新增MIME測試把官方RfcComplianceException誤寫成InvalidArgumentException失敗, 已改正並單獨4項7斷言通過；其餘原385項沒有新增失敗, 正在重跑完整結果
- 發現visual runner原本僅在image不存在才build, 依賴更新後可能用舊vendor驗畫面。改專用可快取build映像, Compose支援指定test image, composer.json／lock納入來源及harness指紋, 不覆寫共用tasktracker-backend標籤
- 389項16981斷言完整通過（1項既有skip）, 新正式映像建置成功；5199／13199專用拓樸的test:smoke與附件HTTP通過。修fixture未定義client, 不改產品附件行為
- 已讀專案新增tasktracker-test-environments, 不再搶一般5175；TT-335其餘改動屬Claude, 本次不stage。視覺與來源穩定核對續跑
- 正式compare首次在tablet任務詳細頁失敗, diff只落在留言「載入紀錄中」及延後出現的圖片工具鈕, 不是Symfony版型變更。既有networkidle仍可能早於Vue啟動按需請求, 改等該合成卡片實際空留言與圖片工具鈕就緒, 不加固定sleep或放寬像素門檻；舊失敗產物保留
- 新環境專項24項174斷言通過（以TASKTRACKER_AUTH_THROTTLE_SCALE=1驗預設限流, Compose=50只供瀏覽器）；第一次未覆寫時AuthThrottleScaleTest失敗已向Claude回覆, 不修改其承辦檔案
- 目前全部產品差異已與Claude提交cf3a546分開；正式映像實際InstalledVersions確認四元件為修補版, 不是只更新lock
- 已依Josh持續授權核准66張載入完成候選, 正式compare使用同一來源與修補vendor；未變更像素門檻, 待通過後才提交
- 最終66張正式比較通過, approved／environmentMatches皆true, 與目前來源一致；完整安全／HTTP／MySQL／冒煙／附件結果及已知限制記docs/symfony-security-update-2026-11-14.md, 本階段可提交並回填
