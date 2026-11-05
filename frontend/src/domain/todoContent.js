// 舊待辦只在編輯時組合原標題及說明，新格式以完整content為準。
export function todoSource(todo) {
  return typeof todo.content === 'string' ? todo.content : [todo.title, todo.note].filter(Boolean).join('\n\n');
}
