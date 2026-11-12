# 個人頁面與通知資料存取拆分

日期：2026-11-12。延續已授權效能改善，處理TT-216／217／218與TT-180、240部分接線，不新增Issue通知／mention或外部PAT。

## 頁面與API責任

首頁只載本人承辦／交辦的目前狀態分頁，每頁20、最大100；scope與狀態條件、搜尋、updatedAt／publicId穩定排序在SQL。摘要只含識別、標題、狀態、最小參與者、能力與可見轉卡連結，不讀原稿、note或歷史。展開／編輯／轉卡才讀單筆原稿；歷史另外20筆分頁。已完成／退回／取消／轉卡集合按需要分頁，不下載全集。

新增PersonalWorkspace、PersonalNotifications、ProjectDirectory與各自query owner；App負責可信身分／Workspace及導覽，不再getTodos。專案目錄獨立20筆分頁與SQL搜尋。首頁／目錄／工作區設定／工時分析沿用最小auth/current，不為進頁先讀Workspace全部卡片。月曆、Project管理、通知預覽仍待遷移；不能宣稱所有整包入口已退役。

通知鈴鐺只讀未讀數與個人版本。收件匣打開才讀當頁通知，查詢同時限制收件人及目前Todo參與資格；退回失去參與資格後不洩漏舊內容。標記已讀仍用原寫入服務交易驗權。個人版本是限定本人列的COUNT／SUM revision／MAX時間與資格版本聚合；ProjectMember異動也影響已轉卡Todo版本。它不下載內容，但仍有SQL聚合成本，尚非持久化O(1)scope計數器，不視為TT-148全部完成。

同步單一排程：30秒基準加jitter；hidden停止、resume重驗，失敗指數退避上限120秒，取消／generation隔離。版本不變不讀Todo列表；版本變動只刷新相關個人資料，不帶Workspace。頁面快取最多50筆原稿、不進storage；未儲存草稿維持原revision，撤權立即清失效摘要與快取。

## 選項與轉卡

Todo承辦選項只讀有效Workspace成員的id／name／email作同名辨識，不附角色歷史。Project負責人：建立用途必須project.create，只列具新Project可見資格的成員；編輯用途先驗該Project的project.update，只列對該Project可見者。轉卡Project候選SQL只列可issue.write的Project，選定後按需hydrate狀態／能力；表單載入時禁止提交半成品，寫入仍重驗。

轉卡連結先由當頁Issue ID集合取得Project參照，批次驗有效資格後才讀最小Issue連結。未授權不讀Issue內容或暴露識別；不存在逐Todo讀取描述／留言／audit的N+1。保留單向轉卡、原稿、原requestId／revision／租戶限制。

## 八項存取審查

1. 呼叫端欄位分成摘要、單筆原稿、歷史、未讀數、收件匣及選項。
2. 所有集合有上限／穩定排序；個人原稿快取限50。
3. scope、狀態、參與者、搜尋及分頁在SQL。
4. 上述頁面不預設下載Workspace；其他管理／月曆仍為未完成P1。
5. 原稿與活動按互動需要讀取；收件匣關閉即卸載取消。
6. 背景只有小版本回應；已轉卡連結批次資格／查詢，版本聚合成本如上，未假稱常數。
7. 測試驗證摘要沒有內容／歷史，其他人不可見，未讀／通知作者限制、撤權無連結；查詢記錄驗證不讀audit及無資格時不查Issue。
8. TT-180、240／241～243／245、146～149、219、月曆／報表／runtime／bundle／容量量測仍有未完成範圍，不能稱整體效能驗收通過。

## 驗證與限制

- 完整預設memory：74 passed／3410 assertions、9 native skips；獨立MySQL：83 passed／3562 assertions。另補0／20／200筆已轉卡分頁與批次SQL檢查，見本階段測試紀錄。
- 真實Chrome隔離API：25筆待辦20＋5、原稿／歷史按需、原稿轉卡、收件人通知及定位、35秒idle只版本檢查，bulkReads=0。
- 設定、Workspace切換／另一分頁409／既有帳號邀請、三種工時分析、Markdown安全及轉卡三尺寸回歸通過。
- API client協定、composables及Vite build通過，Pint本階段21個PHP檔。build仍596.53kB主要JS chunk警告，由TT-183續作。
- 使用獨立人造資料與隔離測試schema，沒有對真實本地／外部業務庫seed或壓測；不是正式p95／RUM證據。資料切回本地詳見local-services-return-2026-11-12.md。
