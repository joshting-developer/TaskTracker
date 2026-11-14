# TT-360 專案交接工具被Cloudflare擋下, 補上固定User-Agent

## 目標

讓 `tools/tasktracker.py` 恢復可以登入正式站, Codex和Claude可以照常領卡、留言、看紙條

## 做法

- 請求固定帶 `User-Agent: tasktracker-agent-cli/1.0`
- 不改Cloudflare設定, 不改共用庫PAT工具

## 驗收

- claude和codex兩個帳號都能登入並讀到資料
- 發紙條通知Codex
