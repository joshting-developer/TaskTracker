# OpenAPI離線驗證來源

- 官方固定Schema：https://spec.openapis.org/oas/3.1/schema/2022-10-07
- 標準：https://spec.openapis.org/oas/v3.1.0.html
- SHA-256：`da01ba28852cac0de53893797cb8d1942bc3b05084f526dcc216717dec314ed0`
- 上游：https://github.com/OAI/OpenAPI-Specification
- 授權：Apache-2.0，原文見LICENSE。下載日期2026-11-12；不修改Schema內容。

以tools/check_openapi.py及固定jsonschema依賴離線驗證OpenAPI結構（含dynamicRef）；PHP端Opis標準模式另驗各operation範例與實際HTTP回應Schema。官方結構Schema本身不是每個業務Schema的完整驗證；兩者搭配，不把此處當作TT-253已完成CI。
