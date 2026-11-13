import { Marked } from 'marked';
import DOMPurify from 'dompurify';

const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));

function safeHref(href) {
  if (/[\u0000-\u0020\u007f]/.test(href)) return false;
  try { return ['http:', 'https:', 'mailto:'].includes(new URL(href, window.location.origin).protocol); }
  catch { return false; }
}

function parserFor(attachments) { return new Marked({
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
    // 只讓伺服器附件參照進入可信占位, 不接受內容提供 src 或 HTML
    image({ href, text }) {
      const label = escapeHtml(text || '圖片');
      if (attachments && /^attachment:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(href) && attachments.length < 20) {
        const slot = attachments.length;
        attachments.push({ id: href.slice(11), label: text || '圖片' });
        return `<span data-attachment-slot="${slot}"></span>`;
      }
      return safeHref(href) ? `<a href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${label}</a>` : label;
    },
  },
}); }

// 所有Markdown閱讀及預覽都從此入口清理，禁止事件、樣式、iframe及SVG等內容。
export function renderMarkdownDocument(source, images = false) {
  const attachments = [];
  const html = DOMPurify.sanitize(parserFor(images ? attachments : null).parse(source || ''), {
    ALLOWED_TAGS: ['p', 'br', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'em', 'del', 'blockquote', 'pre', 'code', 'ul', 'ol', 'li', 'a', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'input', 'span'],
    ALLOWED_ATTR: ['href', 'title', 'target', 'rel', 'start', 'align', 'type', 'disabled', 'checked', 'data-attachment-slot'],
    ALLOW_DATA_ATTR: false,
    ALLOW_ARIA_ATTR: false,
  });
  return { html, attachments };
}

export function renderMarkdown(source) { return renderMarkdownDocument(source).html; }
