# TT-406 GPT Plugin：附件限制、上傳、綁定與下載

沿用180分鐘估時, 6個已驗API的JSON／multipart／binary薄轉接。JSON工具保留16KiB限制；檔案工具獨立6MiB上限, base64輸入轉multipart, 下載回MCP embedded resource, 不回私有URL假稱完成。上傳預覽只展示檔名／MIME／大小／digest, 不回完整base64；綁定同時檢查附件及卡片revision。沿用owner及PAT邊界, 不接受任意檔案URL。

驗Adapter位元組／multipart轉送／上限及權限, 不重測已驗API。原生Host附件輸入／呈現仍須實驗, 未通過前不宣稱完整附件驗收
