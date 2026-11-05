import { Marked } from 'marked';
import DOMPurify from 'dompurify';

const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));

function safeHref(href) {
  if (/[\u0000-\u0020\u007f]/.test(href)) return false;
  try { return ['http:', 'https:', 'mailto:'].includes(new URL(href, window.location.origin).protocol); }
  catch { return false; }
}

const parser = new Marked({
  gfm: true,
  breaks: true,
  async: false,
  renderer: {
    // HTML仍以文字呈現，保留舊內容且不讓使用者插入任意標籤。
    html({ text }) { return escapeHtml(text); },
    heading({ depth, tokens }) {
      const level = Math.min(depth + 1, 6);
      return `<h${level}>${this.parser.parseInline(tokens)}</h${level}>`;
    },
    link({ href, tokens }) {
      const label = this.parser.parseInline(tokens);
      return safeHref(href) ? `<a href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${label}</a>` : label;
    },
    // 圖片以連結顯示，預覽不自動下載外部圖片，也不提供附件上傳。
    image({ href, text }) {
      const label = escapeHtml(text || '圖片');
      return safeHref(href) ? `<a href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${label}</a>` : label;
    },
  },
});

// 所有Markdown閱讀及預覽都從此入口清理，禁止事件、樣式、iframe及SVG等內容。
export function renderMarkdown(source) {
  return DOMPurify.sanitize(parser.parse(source || ''), {
    ALLOWED_TAGS: ['p', 'br', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'em', 'del', 'blockquote', 'pre', 'code', 'ul', 'ol', 'li', 'a', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'input'],
    ALLOWED_ATTR: ['href', 'title', 'target', 'rel', 'start', 'align', 'type', 'disabled', 'checked'],
    ALLOW_DATA_ATTR: false,
    ALLOW_ARIA_ATTR: false,
  });
}
