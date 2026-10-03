import test from 'node:test';
import assert from 'node:assert/strict';
import { receiptLine, receiptTotals } from '../src/models/stockReceipt.mjs';

test('discount is applied before VAT and line amounts round to cents', () => {
    assert.deepEqual(receiptLine({ quantity: 3, price: 10, discount: 10, vat: 23 }), { net: 27, tax: 6.21, total: 33.21 });
    assert.deepEqual(receiptLine({ quantity: 3, price: 0.01, discount: 0, vat: 23 }), { net: 0.03, tax: 0.01, total: 0.04 });
    assert.deepEqual(receiptLine({ quantity: 1, price: 0.01, discount: 50, vat: 0 }), { net: 0.01, tax: 0, total: 0.01 });
});

test('mixed VAT totals sum rounded lines, including zero priced goods', () => {
    assert.deepEqual(receiptTotals([
        { quantity: 3, price: 10, discount: 10, vat: 23 },
        { quantity: 2, price: 5, discount: 0, vat: 0 },
        { quantity: 10, price: 0, discount: 0, vat: 23 },
    ]), { net: 37, tax: 6.21, total: 43.21 });
});
