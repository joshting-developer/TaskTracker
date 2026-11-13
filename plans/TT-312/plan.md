# TT-312 task-tracker-usage加入接任務前先看小待辦與用小待辦交辦

- Josh想用小待辦交代事情：Claude請Codex做事、Agent請Josh複查, 都從小待辦交辦, 不靠對話轉述
- 規則放共用task-tracker-usage, TaskTracker的AGENTS只補一句指過去, 不複製正文
- 兩個CLI都加小待辦指令：共用的PAT CLI（tasktracker_api.py）和TaskTracker自己的Session工具（tools/tasktracker.py）
