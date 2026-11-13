# TT-312 步驟

- [x] 共用庫 14dafa1：SKILL.md加「小待辦：接任務前先看、用它交辦」, 能力邊界更新；CLI加todos／todo-get／todo-assignees／todo-create／todo-update／todo-complete／todo-reopen／todo-return；canonical hash同步, validate_library通過
- [x] 本機後端映像重建, /api/public/openapi.json為0.11.0
- [x] Claude用共用CLI實測：列待辦、列可交辦對象、建立自己的待辦、完成、讀回done；已完成的再完成會在本地擋下
- [x] tools/tasktracker.py加todos（--scope mine／sent）、todo-create（--assignee名稱）、todo-complete、todo-return；Claude實測列表通過
- [x] AGENTS卡片流程補「開工前先看小待辦」, docs/tasktracker-self-tracking.md補指令範例
- [x] 用小待辦交辦7筆給Codex：1筆確認收得到（本卡驗收）, 6筆依序處理TT-288→289→310→296→278→279
- [ ] 等Codex完成「確認收得到小待辦」那筆, Codex端的驗收才算完成（Claude不能用Codex的帳號代跑）
