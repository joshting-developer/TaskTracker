# 執行紀錄

- 依Josh要求多語系先完成再統一部署, TT-308 123f8a9已push, 本地隔離回歸及162張核准比較通過
- 沿用既有TT-155整合驗收卡, 不重複開相同入口部署卡；估90分鐘, 預計單一v0.4.0 tag
- 正式5項migration已由TT-153完成, 目前35項全部套用。部署前仍需讀回比對, 不重跑seed／reset
- Fly帳號與單台nrt／512MB現況核對, 舊映像digest符合v0.3.0, 私有Machine快照保留
- 正式MySQL只讀確認35項migration均已套用, release migration guard通過, 本次不含待執行schema
- 多語系來源main CI 43692820217仍在執行, 等通過再建立單一版本tag
- 2026-11-15 v0.4.0來源固定ba48d05, contract及映像驗證通過, 正式migration無待執行, Fly已成功更新單台HTTP機器
- 首次release run 43693179529在部署後Python匿名registration-policy驗證收到403, 實際HTTPS健康及正式瀏覽器登入／雙語／偏好保存／1440、768、390寬度均已通過。不能把這次CI宣稱成功
- 以具名TaskTrackerRelease/1.0客戶端請求政策端點實測200。修正驗證標頭, 同tag重試只允許image ID與已驗證artifact完全相同, 不覆寫既有版本；不同映像或registry拒絕立即停止
- 12項發布／migration／archive測試通過, 包含同版本續跑、不同image拒絕及registry拒絕不當缺版處理。待push後重跑失敗deploy job, 不移動tag或新建版本
- ff26ad6已push, 同run第二次deploy成功, validate／source／contract／image／deploy全success, 原tag未變
- 正式瀏覽器本人登入、雙語切換與保存、三寬度、Secure／HttpOnly、私有API及靜態快取通過；另驗404、OG圖200、login／app noindex
- TT-155仍有LINE實際貼文預覽及本次冷啟動未驗, 改review明列, 不宣稱所有AC完成。部署與多語系已完成, 交接到TT-348
