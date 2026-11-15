# 公開頁語系契約

TT-365／366

- 公開五頁、登入與App共用瀏覽器的明確選擇`tasktracker:locale`, 首次使用瀏覽器偏好, 不支援語系回繁中
- 原本`tasktracker:locale:<account>`資料保留, 沒有新的瀏覽器選擇時仍可讀舊偏好。新的明確選擇優先, 登出／登入另一帳號不自行切回另一種語言。只共享語系值, 不共享身分或業務資料
- localStorage無權時同頁切換仍可用, 重新整理及跨頁記憶無法保證。storage事件同步其他分頁
- 公開頁翻譯只有可信靜態字典, 替換文字節點與aria-label／alt／title, 不使用innerHTML或翻譯使用者內容。範例API placeholder固定為personal-token
- 切換不重新載入、不呼叫API, 不重建編輯器或清除草稿
- 中文原始HTML、canonical、Open Graph、sitemap仍沿用。英文是同網址的瀏覽器呈現, 這批不新增英文網址或hreflang, 不宣稱英文已獨立索引

## 審查

五頁、兩語系、三尺寸均測完整文字與橫向溢位, 另驗重新整理、公開頁到登入再返回、無儲存權限與切換時沒有API。使用真實Chrome, 不以build取代互動測試

Design Taste回報既有產品示意圖不足四張與功能頁名稱像eyebrow。這批維持已核准產品介紹結構, 不為規則額外加無關照片；輪播頁碼與合成資料標記是功能／來源資訊。這些保留為設計審查意見, 不將工具輸出偽報成全部通過
