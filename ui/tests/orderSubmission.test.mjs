import test, { beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { prepareOrderSubmission, finishOrderSubmission } from '../src/models/orderSubmission.js';
beforeEach(() => {
    const values = new Map();
    globalThis.localStorage = { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value), removeItem: key => values.delete(key) };
});
const payload = () => ({ customer: { company: 'Firma', email: 'buyer@example.test' }, orderProducts: [{ id: 1, input_order: 2 }], shipping_method_id: 1 });
test('retry and concurrent preparation keep the same key', async () => {
    const [a, b] = await Promise.all([prepareOrderSubmission(payload()), prepareOrderSubmission(payload())]);
    assert.equal(a.idempotency_key, b.idempotency_key);
    assert.equal((await prepareOrderSubmission(payload())).idempotency_key, a.idempotency_key);
});
test('changed quantity and completed order start a new submission', async () => {
    const a = await prepareOrderSubmission(payload());
    const modified = payload(); modified.orderProducts[0].input_order = 3;
    const b = await prepareOrderSubmission(modified);
    assert.notEqual(a.idempotency_key, b.idempotency_key);
    finishOrderSubmission();
    assert.notEqual((await prepareOrderSubmission(modified)).idempotency_key, b.idempotency_key);
});
test('different file content and different users do not reuse the key', async () => {
    const file = text => new File([text], 'design.pdf');
    const a = await prepareOrderSubmission(payload(), [file('original')], 'user:1');
    const retry = await prepareOrderSubmission(payload(), [file('original')], 'user:1');
    const changed = await prepareOrderSubmission(payload(), [file('changed')], 'user:1');
    const other = await prepareOrderSubmission(payload(), [file('original')], 'user:2');
    assert.equal(a.idempotency_key, retry.idempotency_key);
    assert.notEqual(a.idempotency_key, changed.idempotency_key);
    assert.notEqual(a.idempotency_key, other.idempotency_key);
});
test('storage contains only a fingerprint and key, never customer data', async () => {
    await prepareOrderSubmission(payload());
    assert.deepEqual(Object.keys(JSON.parse(localStorage.getItem('order-submission-v1:guest'))).sort(), ['fingerprint', 'key']);
    assert.ok(!localStorage.getItem('order-submission-v1:guest').includes('buyer@example.test'));
});
