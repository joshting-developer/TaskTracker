# TT-311 步驟

- [x] PublicTodosController、PublicTodoWriteRequest、路由；BearerTokenSession::writeOnce允許沒有Project
- [x] 測試：PublicTodoApiTest（SQLite＋隔離MySQL）：read Token不能寫、交辦→對方看得到→完成、交辦人不能代完成、409版本、重送不多建、退回回到交辦人、別人的私人待辦404、列表上限
- [x] OpenAPI 0.11.0：7個端點、Todo相關Schema與範例；check_openapi通過；PublicApiContractTest用實際回應比對Schema（成功和400／403／404／409）
- [x] 開發者文件：快速開始、認證與權限、完整範例、Agent指南、HTTP契約都加小待辦；cURL和Python範例加「建立自己的待辦→重送→找到→完成」, documented_examples_check實際跑過
- [x] test:docs五頁三尺寸、Pint、PHPDoc（我的部分0問題）
- [x] 在13049起契約CI同名隔離環境跑public_client_acceptance通過；另外用一次性腳本跑兩個帳號只靠PAT的流程：Josh交辦給Codex→Codex讀到並完成→Codex再交辦給Josh, 通過
