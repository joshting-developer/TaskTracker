# 執行紀錄

- 依Josh要求多語系先完成再統一部署, TT-308 123f8a9已push, 本地隔離回歸及162張核准比較通過
- 沿用既有TT-155整合驗收卡, 不重複開相同入口部署卡；估90分鐘, 預計單一v0.4.0 tag
- 正式5項migration已由TT-153完成, 目前35項全部套用。部署前仍需讀回比對, 不重跑seed／reset
- Fly帳號與單台nrt／512MB現況核對, 舊映像digest符合v0.3.0, 私有Machine快照保留
- 正式MySQL只讀確認35項migration均已套用, release migration guard通過, 本次不含待執行schema
- 多語系來源main CI 43692820217仍在執行, 等通過再建立單一版本tag
