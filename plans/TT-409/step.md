# TT-409 進度

- 改好 .agents/skills/tasktracker-test-environments/SKILL.md 的驗收分層: 第1層每張卡都跑, 改到共用元件、路由、權限或登入時只擴大到受影響頁面；冒煙與完整回歸改成每個patch版或Season整批發布前, 打v* tag前跑一次
- 全專案搜尋test:smoke、冒煙、完整回歸, 沒有其他地方規定每張卡都要冒煙；共用verification.md本來就是大階段收尾才跑完整回歸, 跟這次調整一致
- 這是文件規則調整, 沒有程式異動, 沒跑測試
