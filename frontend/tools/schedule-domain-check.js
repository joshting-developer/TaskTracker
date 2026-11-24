import assert from 'node:assert/strict';
import { addDays, daysBetween, layoutBars, scheduleSpan, weekOf } from '../src/domain/schedule.js';

assert.equal(addDays('2028-02-28', 1), '2028-02-29');
assert.equal(addDays('2027-02-28', 1), '2027-03-01');
assert.equal(addDays('2026-12-31', 1), '2027-01-01');
assert.equal(daysBetween('2026-11-30', '2026-12-06'), 6);
assert.deepEqual(weekOf('2026-11-24'), { from: '2026-11-23', to: '2026-11-29', days: ['2026-11-23', '2026-11-24', '2026-11-25', '2026-11-26', '2026-11-27', '2026-11-28', '2026-11-29'] });
assert.equal(weekOf('2026-11-29').from, '2026-11-23');
assert.equal(weekOf('2026-12-31').to, '2027-01-03');

assert.deepEqual(scheduleSpan({ startDate: '2026-12-01', endDate: '' }), { start: '2026-12-01', end: '2026-12-01' });
assert.deepEqual(scheduleSpan({ startDate: '', endDate: '2026-12-02' }), { start: '2026-12-02', end: '2026-12-02' });
assert.equal(scheduleSpan({ startDate: '', endDate: '', dueDate: '2026-12-01' }), null);

const bars = layoutBars([
  { id: 'across', startDate: '2026-11-20', endDate: '2026-12-31' },
  { id: 'single', startDate: '2026-12-01', endDate: '' },
  { id: 'after-single', startDate: '2026-12-02', endDate: '2026-12-03' },
  { id: 'overlap', startDate: '2026-12-01', endDate: '2026-12-02' },
  { id: 'outside', startDate: '2026-12-07', endDate: '' },
  { id: 'unscheduled', startDate: '', endDate: '' },
], '2026-11-30', '2026-12-06');
assert.deepEqual(bars.map(bar => [bar.issue.id, bar.start, bar.end, bar.lane]), [['across', 0, 6, 0], ['single', 1, 1, 1], ['overlap', 1, 2, 2], ['after-single', 2, 3, 1]]);
assert.deepEqual([bars[0].continuesBefore, bars[0].continuesAfter, bars[1].continuesBefore], [true, true, false]);
console.log('PASS schedule domain: date math across leap/year, Monday weeks, single-ended spans, clipping and non-overlapping lanes');
