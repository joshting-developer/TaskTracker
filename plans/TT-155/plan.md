# TT-155 單一Fly App正式整合驗收

估90分鐘, 多語系TT-308已完成123f8a9並push, 本次統一打一個tag部署, 再接GPT相關卡。主站為正式站, 不seed或建立合成業務資料

1. 保留正式現況、舊映像digest與私有完整MySQL備份, 確認受控migration已全部套用, 不再搬本地舊資料
2. 核對main CI、現有版本與tag。由現行tag workflow建linux/amd64同一受測映像並部署, 不跳過CI、migration guard或使用latest
3. 檢查HTTPS入口、HTML／靜態資產／私有API快取、登入／深連結與繁中英文、本人PAT／專案／既有附件。隔離回歸負責寫入與租戶負例, 正式只做最小既有資料驗讀
4. 回填卡片與正式工時、版本／commit／digest／實測與未驗；完成發布與驗收紙條後重新讀紙條, 再接GPT整合

原正式v0.3.0 digest：sha256:f5824ccd684cbe53121e9bec09daccb25bd43c55852e58d9d9e538f1315ef8a3。回退只回程式, 不用舊備份覆蓋部署後新增資料
