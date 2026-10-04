import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatMoney, formatDateTime } from '../../public/assets/js/lib/locale.js';

test('formatMoney renders KES exactly using integer minor units', () => {
  const out = formatMoney(120000);
  assert.match(out, /1,200/);
  assert.match(out, /Ksh|KES/);
  assert.match(out, /120000|1,200\.00/);
  assert.match(formatMoney(1), /\.01$/);
  assert.equal(formatMoney(2 ** 53 - 1).replace(/\s/g, ' '), 'Ksh 90,071,992,547,409.91');
  assert.throws(() => formatMoney(1.5), TypeError);
  assert.throws(() => formatMoney(-1), TypeError);
  assert.throws(() => formatMoney(-0), TypeError);
  assert.throws(() => formatMoney(2 ** 53), TypeError);
  assert.throws(() => formatMoney('100'), TypeError);
});

test('formatDateTime renders Africa/Nairobi wall time', () => {
  const out = formatDateTime('2026-10-04T09:30:00Z');
  assert.match(out, /2026/);
  assert.match(out, /12:30/); // UTC+3
  assert.throws(() => formatDateTime('not-a-date'), TypeError);
  assert.throws(() => formatDateTime(null), TypeError);
  assert.throws(() => formatDateTime(0), TypeError);
});
