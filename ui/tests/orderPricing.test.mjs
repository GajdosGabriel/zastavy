import test from 'node:test';
import assert from 'node:assert/strict';
import { adjustmentAmount } from '../src/models/orderPricing.js';

test('percentage adjustments round half cents consistently with server pricing', () => {
    assert.equal(adjustmentAmount(2.01, { direction: 'discount', type: 'percent', value: 50 }), -1.01);
    assert.equal(adjustmentAmount(125, { direction: 'surcharge', type: 'percent', value: 10 }), 12.5);
});

test('combined discounts cannot consume delivery and payment fees', () => {
    assert.equal(adjustmentAmount(44.9, { direction: 'discount', type: 'fixed', value: 100 }, 3.45), -41.45);
    assert.equal(adjustmentAmount(20, { direction: 'discount', type: 'percent', value: 10 }, 20), 0);
    assert.equal(adjustmentAmount(20, null), 0);
});
