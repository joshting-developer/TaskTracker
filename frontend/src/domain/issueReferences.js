// Only text nodes outside existing links/code opt in; identifiers never supply arbitrary URLs.
export function issueReferenceParts(text) {
  const pattern = /(?<![A-Za-z0-9_/#-])#?([A-Z][A-Z0-9]{0,19}-[1-9][0-9]{0,9})(?![A-Za-z0-9_-])/g;
  const result = []; let cursor = 0;
  for (const match of text.matchAll(pattern)) {
    if (match.index > cursor) result.push({ text: text.slice(cursor, match.index) });
    result.push({ text: match[0], identifier: match[1] });
    cursor = match.index + match[0].length;
  }
  if (cursor < text.length) result.push({ text: text.slice(cursor) });
  return result;
}
