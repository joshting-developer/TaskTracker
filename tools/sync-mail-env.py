#!/usr/bin/env python3
"""Copy only MAIL_* lines from canonical backend/.env into ignored Compose input."""
import argparse
import base64
import os
import json
import subprocess
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
KEYS = {'MAIL_MAILER', 'MAIL_SCHEME', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'MAIL_EHLO_DOMAIN'}


def sync(source: Path, destination: Path) -> int:
    """Preserve dotenv quoting without loading unrelated database/key settings."""
    rows = []
    for line in source.read_text().splitlines():
        match = re.match(r'^\s*([A-Z_]+)\s*=', line)
        if not match or match[1] not in KEYS:
            continue
        value = line.split('=', 1)[1].strip()
        if value.startswith(('"', "'")) and not value.endswith(value[0]):
            raise ValueError('MAIL設定只支援單行dotenv值')
        rows.append(line.strip())
    if not rows:
        raise ValueError('找不到MAIL設定, 未變更容器輸入')
    # Use the same dotenv parser as Laravel, with private input over stdin only.
    program = "require '/app/vendor/autoload.php'; try {$values=Dotenv\\Dotenv::parse(stream_get_contents(STDIN)); foreach($values as $key=>$value) {putenv($key.'='.$value); $_ENV[$key]=$_SERVER[$key]=$value;} foreach($values as $key=>$value) {$values[$key]=env($key);} echo json_encode($values, JSON_THROW_ON_ERROR);} catch (Throwable $e) {exit(1);}"
    result = subprocess.run(['docker', 'compose', 'exec', '-T', 'backend', 'php', '-r', program], input='APP_NAME=TaskTracker\n' + '\n'.join(rows), text=True, capture_output=True)
    if result.returncode != 0:
        raise ValueError('MAIL設定解析失敗')
    values = {key: value for key, value in json.loads(result.stdout).items() if key in KEYS}
    if any(isinstance(value, str) and any(ord(char) < 32 or ord(char) == 127 for char in value) for value in values.values()):
        raise ValueError('MAIL設定只支援單行值')
    encoded = base64.b64encode(json.dumps(values, ensure_ascii=False).encode()).decode()
    rows = ['TASKTRACKER_MAIL_SETTINGS_B64=' + encoded]
    destination.parent.mkdir(parents=True, exist_ok=True)
    temporary = destination.with_suffix('.tmp')
    with os.fdopen(os.open(temporary, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600), 'w') as handle:
        handle.write('# Generated from backend/.env; edit the source, never this file\n' + '\n'.join(rows) + '\n')
    temporary.replace(destination)
    destination.chmod(0o600)
    selector = ROOT / '.env'
    contents = selector.read_text() if selector.exists() else ''
    key = 'TASKTRACKER_MAIL_ENV_FILE'
    line = key + '=.local/tasktracker-mail.env'
    if re.search(r'^' + key + r'=.*$', contents, re.M):
        contents = re.sub(r'^' + key + r'=.*$', line, contents, flags=re.M)
    else:
        contents = contents.rstrip('\n') + '\n' + line + '\n'
    selector_temp = destination.parent / 'mail-selector.tmp'
    with os.fdopen(os.open(selector_temp, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600), 'w') as handle:
        handle.write(contents)
    selector_temp.replace(selector)
    selector.chmod(0o600)
    return len(values)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.parse_args()
    try:
        count = sync(ROOT / 'backend/.env', ROOT / '.local/tasktracker-mail.env')
        print(f'Synced {count} MAIL settings; values are not printed')
    except (OSError, ValueError, subprocess.SubprocessError):
        raise SystemExit('無法同步MAIL設定, 請檢查backend/.env的單行格式及權限') from None
