# TT-403 GPT Plugin：增量任務變更與刪除同步

沿用90分鐘估時, 兩個GET薄轉接工具, 每批20筆, 原樣轉送cursor／updatedSince／after及filter, 保留nextCursor／syncUntil／nextAfter。每次重驗身分與上游權限, 不自動翻頁或自行編碼cursor。依Josh指示只驗Adapter契約、單頁請求及輸出, 不重測API同步核心
