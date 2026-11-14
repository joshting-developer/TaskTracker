// Legacy entry point: the paper-note UI replaces the old home checklist.
// Notes cover handoff/completion/privacy, Markdown covers edit/conversion/XSS,
// collaboration retains the real PAT CLI integration. API return/cancel/replay
// remain covered by TodoTest and TodoCollaborationCases in both engines.
import { execFileSync } from 'node:child_process';
for (const file of ['notes-page-check.js', 'markdown-check.js', 'todo-collaboration-check.js']) {
  execFileSync(process.execPath, ['tools/' + file], { stdio: 'inherit', env: process.env });
}
