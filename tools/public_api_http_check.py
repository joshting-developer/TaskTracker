#!/usr/bin/env python3
"""只對隔離測試服務驗證真HTTP限流／Retry-After，不讀寫業務資料。"""
import json
import os
import urllib.error
import urllib.request

BASE = os.environ.get('E2E_API_URL')
if BASE not in {'http://127.0.0.1:13029', 'http://127.0.0.1:13049'}:
    raise SystemExit('只允許隔離13029測試API，禁止對現用主服務送限流測試')


def get(path):
    try:
        response = urllib.request.urlopen(BASE + path, timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.headers, response.read()


def main():
    for path in ['/api/public/openapi.json', '/api/public/contract.md']:
        status, headers, _ = get(path)
        assert status == 200
        assert 'Set-Cookie' not in headers
    limited = None
    for _ in range(130):
        status, headers, body = get('/api/public/v1/me')
        if status == 429:
            limited = (headers, body)
            break
        assert status == 401
    assert limited, '真HTTP沒有在上限內產生429'
    headers, body = limited
    assert json.loads(body)['error']['code'] == 'RATE_LIMIT'
    assert 1 <= int(headers['Retry-After']) <= 60
    assert headers['X-RateLimit-Limit'] == '120'
    assert headers['X-RateLimit-Remaining'] == '0'
    assert int(headers['X-RateLimit-Reset']) > 0
    assert get('/api/public/openapi.json')[0] == 200
    print('PASS: 真HTTP 401→429、Retry-After／rate headers、匿名規格不耗配額且無Session')


if __name__ == '__main__':
    main()
