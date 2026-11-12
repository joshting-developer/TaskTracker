<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }} · TaskTracker API</title>
  <link rel="stylesheet" href="/api/public/docs/assets/styles.css">
</head>
<body>
  <a class="skip" href="#content">跳到文件內容</a>
  <header><a class="brand" href="/api/public/docs">TaskTracker<span>API 文件</span></a><a href="/api/public/openapi.json">OpenAPI {{ $version }}</a></header>
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
