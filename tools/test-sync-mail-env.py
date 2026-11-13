#!/usr/bin/env python3
"""Synthetic-only checks for MAIL isolation, literal credentials and private file modes."""
import importlib.util
import base64
import json
import subprocess
import tempfile
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('mail_sync', Path(__file__).with_name('sync-mail-env.py'))
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
with tempfile.TemporaryDirectory(prefix='tt-mail-env-') as folder:
    root = Path(folder)
    source = root / 'source.env'
    destination = root / '.local/tasktracker-mail.env'
    source.write_text('APP_KEY=must-not-copy\nDB_PASSWORD=must-not-copy\nMAIL_HOST=smtp.example.test\nMAIL_SCHEME=null\nMAIL_PASSWORD="synthetic"\n')
    (root / '.env').write_text('APP_KEY=existing-key\nUNCHANGED=value\n')
    values = {'MAIL_HOST': 'smtp.example.test', 'MAIL_SCHEME': None, 'MAIL_PASSWORD': "literal$DOLLAR#hash'quote\\slash", 'APP_KEY': 'must-not-copy'}
    with patch.object(module, 'ROOT', root), patch.object(module.subprocess, 'run', return_value=SimpleNamespace(returncode=0, stdout=json.dumps(values))) as runner:
        assert module.sync(source, destination) == 3
        assert 'DB_PASSWORD' not in runner.call_args.kwargs['input']
        assert 'APP_KEY' not in runner.call_args.kwargs['input']
    assert destination.stat().st_mode & 0o777 == 0o600
    assert 'APP_KEY=existing-key' in (root / '.env').read_text()
    assert 'UNCHANGED=value' in (root / '.env').read_text()
    assert 'must-not-copy' not in destination.read_text()
    compose = root / 'compose.yml'
    compose.write_text('services:\n  test:\n    image: alpine\n    env_file:\n      - '+str(destination)+'\n')
    result = subprocess.run(['docker', 'compose', '-f', str(compose), 'config', '--format', 'json'], capture_output=True, text=True)
    assert result.returncode == 0, 'Synthetic Compose configuration rejected'
    parsed = json.loads(result.stdout)['services']['test']['environment']
    snapshot = json.loads(base64.b64decode(parsed['TASKTRACKER_MAIL_SETTINGS_B64']))
    assert snapshot['MAIL_PASSWORD'] == values['MAIL_PASSWORD'], 'Literal MAIL password changed during Compose parsing'
    assert 'APP_KEY' not in snapshot
    assert snapshot['MAIL_SCHEME'] is None
    assert 'APP_KEY' not in parsed
with tempfile.TemporaryDirectory(prefix='tt-mail-parser-') as folder:
    root = Path(folder)
    source = root / 'source.env'
    destination = root / '.local/tasktracker-mail.env'
    source.write_text('MAIL_HOST=smtp.example.test\nMAIL_SCHEME=null\nMAIL_PASSWORD="synthetic$DOLLAR#text"\n')
    with patch.object(module, 'ROOT', root):
        assert module.sync(source, destination) == 3
    encoded = destination.read_text().split('TASKTRACKER_MAIL_SETTINGS_B64=', 1)[1].strip()
    values = json.loads(base64.b64decode(encoded))
    assert values['MAIL_SCHEME'] is None
    assert values['MAIL_PASSWORD'] == 'synthetic$DOLLAR#text'
print('PASS: MAIL-only projection, private modes, literal characters and native Laravel nullable settings')
