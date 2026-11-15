# 執行紀錄

- 已讀 Josh 截圖描述, 確認 GuestTopBar 仍固定顯示目前登入連結, 手機版又將 nav 強制分到下一行
- TT-193 已提交並待整批發布, 本卡才開始 claim
- 真實幾何量測找到語系控制偏移 8px：LocalePicker scoped margin-bottom 16px 蓋過同權重的外部規則, 已提高父層選擇器明確性而不加 !important
- 登入時不顯示登入連結；註冊時改為只提供登入與介紹, 不再重複註冊入口
- 中英文三尺寸無橫向溢出, 導覽與語系控制中心差小於 2px, 點擊區至少 44px；既有 guest-pages 真實流程通過
