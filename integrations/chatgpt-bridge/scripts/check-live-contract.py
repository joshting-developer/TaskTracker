"""Compare the thin Adapter with the deployed public contract; no PAT is used."""
import json
from pathlib import Path
import urllib.request

source = (Path(__file__).resolve().parents[1] / 'worker/contract.js').read_text()
tools = json.loads(source.split('export const TOOLS = ', 1)[1].strip().removesuffix(';'))
with urllib.request.urlopen('https://task-tracker.josh-ting.com/api/public/openapi.json', timeout=15) as response:
    spec = json.load(response)
operations = {op['operationId']: (method.upper(), path)
              for path, methods in spec['paths'].items() if path.startswith('/api/public/v1/')
              for method, op in methods.items() if method in ('get', 'post', 'put', 'patch', 'delete')}
mapped = {tool['operationId'] for tool in tools.values()}
assert mapped == set(operations), {'missing': sorted(set(operations) - mapped), 'extra': sorted(mapped - set(operations))}
assert len(mapped) == len(tools), 'Duplicate operation mappings'
for name, tool in tools.items():
    assert (tool.get('method', 'GET'), tool['path']) == operations[tool['operationId']], name
print(f"OpenAPI {spec['info']['version']}: {len(tools)} tools cover all deployed PAT operations; methods and paths match")
