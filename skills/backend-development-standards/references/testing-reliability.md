# 後端測試可靠性

## Fixture

- 測試建立自己需要的資料，不依賴本機、CI 或既有 testing DB 恰好存在的 seed。
- 固定主鍵前先確認 factory、seeder、平行測試與其他 fixture 的範圍；能使用回傳 model 或自然鍵時，不硬編主鍵。
- 每個 case 可獨立執行、重跑且不依賴測試順序；檔案與外部狀態亦同。
- fixture schema 需兼容多版本時，可在測試端檢查欄位存在，但不要為修測試修改 production schema。

## Assertion 與 mock

- assertion 驗證 production contract，不讓 mock 自行發明 error code、translation key、status 或 payload 後再把同值當 expected。
- mock 掉外部副作用時，必要 collaborator interaction 仍要以 expectation 證明。
- DB 行為是測試主體時保留真實 DB 流程；不要為了讓測試容易而全部 mock。
- 不用無語意 assertion 或 skip 掩蓋 fixture 問題。

## 驗證階梯

1. 先跑直接涵蓋變更的 test method。
2. 再跑完整測試檔。
3. 共享 helper、base class、global/static state 有變更時，擴大到受影響資料夾或 suite。
4. rebase、合併或套用他人版本後，先前結果失效，至少重跑負責範圍。

若環境阻擋 runtime 測試，明確列出未驗證範圍；lint 與 diff check 不等於測試通過。
