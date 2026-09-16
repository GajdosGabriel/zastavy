import test from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';
import { createRequestActivity, installRequestActivity } from '../src/models/httpActivity.js';
import { createLatestRequest } from '../src/models/latestRequest.js';

const deferred = () => {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
};
const tick = () => new Promise(resolve => setImmediate(resolve));

test('loading remains active until overlapping requests finish, including errors', async () => {
    const pending = [deferred(), deferred()];
    let index = 0;
    const client = axios.create({ adapter: config => pending[index++].promise.then(data => ({ data, config, status: 200, headers: {} })) });
    const activity = createRequestActivity();
    installRequestActivity(client, activity);
    const first = client.get('/first');
    const second = client.get('/second');
    const rejected = assert.rejects(second, /failed/);
    await tick();
    assert.equal(activity.pendingRequests, 2);
    pending[0].resolve('done');
    await first;
    assert.equal(activity.isLoading, true);
    pending[1].reject(new Error('failed'));
    await rejected;
    assert.equal(activity.pendingRequests, 0);
    assert.equal(activity.isLoading, false);
});

test('request cancelled before transport does not leave loading active', async () => {
    const client = axios.create();
    const activity = createRequestActivity();
    installRequestActivity(client, activity);
    const controller = new AbortController();
    controller.abort();
    await assert.rejects(client.get('/cancelled', { signal: controller.signal }), error => axios.isCancel(error));
    assert.equal(activity.pendingRequests, 0);
});

test('late filter response cannot replace the newer results or paginator', async () => {
    const requests = createLatestRequest();
    const old = deferred(), recent = deferred();
    const values = [];
    const first = requests.run(() => old.promise, value => values.push(value), assert.fail);
    const second = requests.run(() => recent.promise, value => values.push(value), assert.fail);
    recent.resolve({ data: ['new'], page: 1 }); await second;
    old.resolve({ data: ['old'], page: 4 }); await first;
    assert.deepEqual(values, [{ data: ['new'], page: 1 }]);
});

test('old failures are ignored while the current failure is reported', async () => {
    const requests = createLatestRequest();
    const old = deferred(), recent = deferred();
    const errors = [];
    const first = requests.run(() => old.promise, assert.fail, error => errors.push(error.message));
    const second = requests.run(() => recent.promise, assert.fail, error => errors.push(error.message));
    old.reject(new Error('old')); await first;
    recent.reject(new Error('current')); await second;
    assert.deepEqual(errors, ['current']);
});
