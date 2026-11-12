#!/usr/bin/env python3
"""證明官方Schema gate會拒絕結構錯誤，不修改唯一正式規格。"""
import copy
import json
import unittest

from jsonschema import Draft202012Validator
from check_openapi import ROOT


class OfficialSchemaGateTest(unittest.TestCase):
    def test_version_info_and_response_structure_drift_are_rejected(self):
        spec = json.loads((ROOT / 'backend/resources/openapi.json').read_text())
        schema = json.loads((ROOT / 'backend/tests/Fixtures/openapi/schema-2022-10-07.json').read_text())
        validator = Draft202012Validator(schema)
        self.assertTrue(validator.is_valid(spec))
        mutations = [copy.deepcopy(spec) for _ in range(3)]
        del mutations[0]['info']['version']
        mutations[1]['openapi'] = 'invalid'
        mutations[2]['paths']['/api/public/v1/me']['get']['responses'] = []
        for candidate in mutations:
            self.assertFalse(validator.is_valid(candidate))


if __name__ == '__main__':
    unittest.main()
