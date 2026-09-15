const storageKey = (scope) => 'order-submission-v1:' + scope;
const canonical = (value) => {
    if (Array.isArray(value)) return value.map(canonical);
    if (value && typeof value === 'object') return Object.fromEntries(Object.keys(value).sort().map(key => [key, canonical(value[key])]));
    return value == null ? null : String(value);
};
const digest = async (bytes) => Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', bytes)), value => value.toString(16).padStart(2, '0')).join('');

export async function prepareOrderSubmission(payload, files = [], scope = 'guest') {
    const clean = { ...payload };
    delete clean.idempotency_key;
    if (clean.customer) {
        const fields = ['id', 'company', 'name', 'email', 'phone', 'street', 'postcode', 'city', 'ico', 'dic', 'ic_dic'];
        clean.customer = Object.fromEntries(fields.filter(key => clean.customer[key] !== undefined).map(key => [key, clean.customer[key]]));
    }
    const fileHashes = await Promise.all(files.map(async file => ({ name: file.name, hash: await digest(await file.arrayBuffer()) })));
    const fingerprint = await digest(new TextEncoder().encode(JSON.stringify(canonical({ payload: clean, files: fileHashes }))));
    let previous;
    try { previous = JSON.parse(localStorage.getItem(storageKey(scope)) || 'null'); } catch { previous = null; }
    const key = previous?.fingerprint === fingerprint ? previous.key : crypto.randomUUID();
    localStorage.setItem(storageKey(scope), JSON.stringify({ fingerprint, key }));
    return { ...clean, idempotency_key: key };
}

export function finishOrderSubmission(scope = 'guest') {
    localStorage.removeItem(storageKey(scope));
}
