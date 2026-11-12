#!/usr/bin/env python3
"""離線驗證正式OpenAPI結構；依賴只安裝於專案私有venv，不連業務API。"""
import argparse
import json
from pathlib import Path

from jsonschema import Draft202012Validator

ROOT = Path(__file__).resolve().parents[1]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--spec', type=Path, default=ROOT / 'backend/resources/openapi.json')
    args = parser.parse_args()
    schema = json.loads((ROOT / 'backend/tests/Fixtures/openapi/schema-2022-10-07.json').read_text())
    spec = json.loads(args.spec.read_text())
    errors = list(Draft202012Validator(schema).iter_errors(spec))
    if errors:
        # 僅輸出Schema差異路徑，不印文件值、範例或可能的秘密。
        for error in errors[:10]:
            print(f'FAIL: {error.json_path} ({error.validator})')
        return 1
    print('PASS: 正式OpenAPI符合固定官方3.1結構Schema（離線）')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
