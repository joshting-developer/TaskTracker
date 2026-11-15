# v0.5.0 整批驗證

## 範圍

TT-363品牌入口、TT-364獨立管理後台、TT-365公開五頁中英文、TT-366上方語言入口, 合併同一版本。GPT私人Plugin不屬Fly主服務映像, TT-350依最新帳號更正暫停；不得把目前登入帳號當成joshting.1999歸屬證據

## 隔離驗證

- 完整後端504項通過、1項既有skip, 19261斷言；後續管理／fixture防護17項通過
- 一般73支檢查加隔離及build共75項均通過。第一輪browser／bundle／issue-sort／uiux失敗紀錄保留, 精準重測成功；未覆寫成第一輪全綠
- policy、inline、pagination、seed、realtime、r2、runtime七組專用拓樸全部通過。R2只使用隔離task-tracker-local, 未用正式bucket跑壓測
- 162張核准畫面compare通過, spacing／overflow／hide-action三種退化注入都被抓到, 還原亦通過。threshold0.02及maxDiffPixels0不變, 舊基準已封存保留
- 本機正式映像與入口／圖示／manifest／公開契約smoke通過；本機arm64不當作CI amd64映像驗收
- 管理6張核准比較通過。公開頁最後額外比較發現兩張差異, 修正為每URL／尺寸開新頁及字型／兩幀就緒後, 30張重新審閱核准, 獨立比較完全相同；原失敗保留, 未放寬閾值
- Skill evidence及靜態資料存取審查通過。公開頁Design Taste仍有既有照片數／feature-name意見, 維持已核准產品示意內容, 不宣稱所有審美規則通過

## 正式發布

尚未打tag或部署。預計v0.5.0由既有Tagged release CI驗來源、契約與amd64映像, 再部署同一映像。正式管理名稱與密碼雜湊已stage, 沒有改一般User或Owner權限

沒有schema異動, 不同步本地合成資料、不seed正式庫。前版保留v0.4.0, digest sha256:f6593b37e10c5343495ffeea14fe4c035306590223da7a8d1121e80f98d6bd9e, 回退只換映像, 不覆寫資料

## 待正式確認

- CI及Fly整批部署成功, 讀回版本與映像digest
- HTTPS、公開語系、登入與App、獨立管理資格及匿名拒絕
- 既有Codex PAT讀卡與Workspace資格仍有效
- GPT私人服務等待切回joshting.1999且Josh明確要求恢復, 暫停Plugin／PAT／Secret操作, 不因主站部署成功就結案TT-350
