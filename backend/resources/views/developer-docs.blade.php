<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }} · TaskTracker API</title>
  <link rel="stylesheet" href="/api/public/docs/assets/styles.css">
</head>
<body>
  <a class="skip" href="#content">跳到文件內容</a>
  {{-- TT-299：品牌回網站首頁, 右側明確提供回到TaskTracker；已登入者由首頁自動進工作空間 --}}
  <header><div class="brand"><a class="brand-site" href="/" aria-label="TaskTracker 首頁">TaskTracker</a><a class="brand-docs" href="/api/public/docs">API 文件</a></div><div class="header-links"><a href="/">回到 TaskTracker</a><a href="/api/public/openapi.json">OpenAPI {{ $version }}</a></div></header>
  <div class="layout">
    <nav aria-label="開發者文件">
      @foreach($pages as $key=>$label)
        <a href="/api/public/docs/{{ $key }}" @if($slug===$key) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    </nav>
    <main id="content" tabindex="-1">
      <div class="document-meta"><span>API v1</span><a href="/api/public/docs/{{ $slug }}.md">Markdown 原文</a></div>
      <article>{!! $html !!}</article>
    </main>
  </div>
</body>
</html>
