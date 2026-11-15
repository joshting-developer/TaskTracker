import assert from 'node:assert/strict';
import { validateIssue } from '../src/domain/model.js';
const input = dates => ({ title: 'Dates', ...dates });
for (const type of ['epic', 'task', 'bug']) {
  const start = validateIssue({ ...input({ startDate: '2028-02-29', dueDate: '2028-02-20' }), type });
  assert.equal(start.startDate, '2028-02-29'); assert.equal(start.endDate, ''); assert.equal(start.dueDate, '2028-02-20');
  assert.equal(validateIssue({ ...input({ endDate: '2028-03-01' }), type }).startDate, '');
}
assert.equal(validateIssue(input({ startDate: '2028-02-29', endDate: '2028-02-29' })).endDate, '2028-02-29');
assert.equal(validateIssue(input({ startDate: null, endDate: '' })).startDate, '');
for (const startDate of ['2027-02-29', '2028-2-29', '0001-01-01', '2028-02-29T00:00:00Z']) assert.throws(() => validateIssue(input({ startDate })));
assert.throws(() => validateIssue(input({ startDate: '2028-03-02', endDate: '2028-03-01' })));
console.log('PASS planned dates: optional endpoints, leap day, same day, independent deadline, clearing and invalid ranges');
