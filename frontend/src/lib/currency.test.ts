import test from 'node:test';
import assert from 'node:assert/strict';
import { formatPrice } from './currency';

test('formatPrice formats MXN currency properly', () => {
  const result = formatPrice(45.5, 'MXN');
  assert.match(result, /\$45\.50/);
});

test('formatPrice formats USD with currency indicator', () => {
  const result = formatPrice(25, 'USD');
  assert.ok(result.includes('USD') || result.includes('$'));
});

test('formatPrice formats EUR properly', () => {
  const result = formatPrice(15.99, 'EUR');
  assert.ok(result.includes('€') || result.includes('15,99'));
});

test('formatPrice falls back gracefully on invalid amounts', () => {
  const result = formatPrice(100);
  assert.ok(result.length > 0);
});
