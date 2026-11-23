#!/bin/sh
# Inspect and exercise only this synthetic runtime container, without production data or secrets.
set -eu
image=${1:?image required}
# TT-377：第二個參數是介紹站 origin 時, 驗 app 建置的舊公開網址轉址；省略時驗合併建置
site=${2:-}
name="tasktracker-release-smoke-${GITHUB_RUN_ID:-local}-$$"
cleanup() { docker rm -f "$name" >/dev/null 2>&1 || true; }
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
key=$(python3 -c 'import base64,secrets; print("base64:"+base64.b64encode(secrets.token_bytes(32)).decode())')
docker run -d --name "$name" -p 127.0.0.1::8080 \
  -e APP_KEY="$key" -e APP_ENV=testing -e SESSION_DRIVER=array \
  -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e BROADCAST_CONNECTION=null \
  -e TASKTRACKER_PUBLIC_REGISTRATION=true -e APP_URL=http://app.smoke.test "$image" >/dev/null
port=$(docker port "$name" 8080/tcp | sed 's/.*://')
python3 - "$port" "$site" <<'PY'
import json,sys,time,urllib.request,urllib.error,urllib.parse
from html.parser import HTMLParser
base='http://127.0.0.1:'+sys.argv[1]
site=sys.argv[2]
for attempt in range(45):
    try:
        with urllib.request.urlopen(base+'/api/health', timeout=2) as r:
            if r.status==200: break
    except (urllib.error.URLError,OSError): pass
    time.sleep(1)
else: raise SystemExit('Synthetic runtime did not become healthy')
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None
opener=urllib.request.build_opener(NoRedirect)
def moved(path,host=None):
    try: opener.open(urllib.request.Request(base+path,headers={'Host':host} if host else {}),timeout=5); raise SystemExit(path+' did not redirect')
    except urllib.error.HTTPError as e: return e.code,e.headers.get('Location')
if site:
    # 舊網域的公開頁 301 到介紹站, 工作區網域的根路徑進 /app/；/login、/app/、/api 照常服務
    for path,target in [('/',site+'/'),('/?intro=1',site+'/?intro=1'),('/index.html',site+'/'),('/about',site+'/'),('/en',site+'/en/'),('/en/',site+'/en/'),('/features/time/',site+'/#time'),('/features/other',site+'/'),('/sitemap.xml',site+'/sitemap.xml')]:
        assert moved(path,'legacy.smoke.test')==(301,target),(path,moved(path,'legacy.smoke.test'))
    assert moved('/','app.smoke.test')==(302,'/app/'),moved('/','app.smoke.test')
    for path in ['/login','/app/','/admin/','/api/health']:
        with urllib.request.urlopen(base+path,timeout=5) as r: assert r.status==200,path
    with urllib.request.urlopen(base+'/robots.txt',timeout=5) as r: robots=r.read().decode()
    assert 'Disallow: /app/' in robots and 'Disallow: /\n' not in robots,robots
    print('PASS: app build redirects legacy public URLs to the site and keeps workspace routes')
    raise SystemExit(0)
for path in ['/', '/en/', '/login', '/app/', '/admin/', '/api/health', '/api/public/openapi.json']:
    with urllib.request.urlopen(base+path,timeout=5) as r:
        assert r.status==200,path
        if path=='/api/public/openapi.json': assert json.load(r)['openapi'].startswith('3.1')
for path,target in [('/en','/en/'),('/about','/'),('/features/tasks/','/#tasks'),('/features/collaboration','/#collaboration'),('/features/time/index.html','/#time'),('/features/integrations/','/#integrations'),('/features/other/','/')]:
    assert moved(path)==(301,target),(path,moved(path))
with urllib.request.urlopen(base+'/en/',timeout=5) as r: english=r.read().decode()
assert '<html lang="en">' in english and 'hreflang="zh-Hant"' in english and 'application/ld+json' in english
with urllib.request.urlopen(base+'/admin/',timeout=5) as r:
    assert 'noindex' in r.headers.get('X-Robots-Tag','')
    assert 'id="admin"' in r.read().decode()
class Icons(HTMLParser):
    def __init__(self): super().__init__(); self.icons=[]
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs)
        if tag=='link' and attrs.get('rel') in ['icon','apple-touch-icon']: self.icons.append(attrs['href'])
for path in ['/', '/en/', '/login', '/app/', '/admin/']:
    with urllib.request.urlopen(base+path,timeout=5) as r: html=r.read().decode()
    parser=Icons();parser.feed(html);assert len(parser.icons)==6,path
    for href in parser.icons:
        url=urllib.parse.urljoin(base+path,href);assert url.startswith(base+'/assets/')
        with urllib.request.urlopen(url,timeout=5) as r:
            assert r.status==200 and r.headers.get_content_type().startswith('image/'),href
            assert 'immutable' in r.headers.get('Cache-Control',''),href
with urllib.request.urlopen(base+'/site.webmanifest',timeout=5) as r:
    assert r.headers.get_content_type()=='application/manifest+json'
    assert 'no-cache' in r.headers.get('Cache-Control','')
    manifest=json.load(r);assert manifest['start_url']=='/app/' and len(manifest['icons'])==3
for icon in manifest['icons']:
    with urllib.request.urlopen(base+icon['src'],timeout=5) as r:
        assert r.status==200 and r.headers.get_content_type()=='image/png'
        assert 'immutable' in r.headers.get('Cache-Control','')
with urllib.request.urlopen(base+'/favicon.ico',timeout=5) as r: assert r.status==200
print('PASS: all entrypoint icons, hashed cache, manifest MIME and versioned device assets')
with urllib.request.urlopen(base+'/api/auth/registration-policy',timeout=5) as r:
    assert json.load(r)['data']['publicRegistration'] is True
print('PASS: synthetic production runtime routes and public contract')
PY
docker exec "$name" sh -c 'test ! -e /app/.env && test ! -d /app/tests && test ! -d /app/tools && test ! -d /app/node_modules && test ! -d /app/.git'
docker exec "$name" php -r 'if ((int) ini_get("mysqlnd.net_read_timeout") !== 3) exit(1); echo "PASS: bounded mysqlnd reads\n";'
