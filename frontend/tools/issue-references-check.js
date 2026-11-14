import assert from 'node:assert/strict';
import { issueReferenceParts } from '../src/domain/issueReferences.js';
const ids = value => issueReferenceParts(value).filter(part => part.identifier).map(part => part.identifier);
assert.deepEqual(ids('處理TT-331, 再看#TT-332 與 MA-8'), ['TT-331', 'TT-332', 'MA-8']);
for (const value of ['fooTT-331', '/TT-331', 'https://x/TT-331', '#TT-331suffix', 'TT-0', 'TT-01', 'TT-331-extra', 'TT-12345678901', 'x_TT-331', '##TT-331']) assert.deepEqual(ids(value), [], value);
for (const value of ['', '沒有卡號', '#TT-331 與TT-331', '（TT-331）', '<script>TT-331</script>']) assert.equal(issueReferenceParts(value).map(part => part.text).join(''), value);
console.log('PASS: bounded card references preserve text, support optional #, reject partial identifiers and URL paths');
