const money = (value) => Math.round((value + Number.EPSILON) * 100) / 100;

export const receiptUuid = () => {
    if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    // Lokálny HTTP host nemusí mať randomUUID; getRandomValues funguje aj tam.
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
};

export const receiptLine = (item) => {
    const scaled = (value) => BigInt(Math.round((Number(value) || 0) * 100));
    const quantity = Math.trunc(Number(item.quantity) || 0);
    if (![quantity, Number(item.price), Number(item.discount), Number(item.vat)].every(Number.isFinite)) return { net: 0, tax: 0, total: 0 };
    const netCents = (BigInt(quantity) * scaled(item.price) * (10000n - scaled(item.discount)) + 5000n) / 10000n;
    const taxCents = (netCents * scaled(item.vat) + 5000n) / 10000n;
    return { net: Number(netCents) / 100, tax: Number(taxCents) / 100, total: Number(netCents + taxCents) / 100 };
};

export const receiptTotals = (items) => {
    const lines = items.map(receiptLine);
    const net = money(lines.reduce((sum, line) => sum + line.net, 0));
    const tax = money(lines.reduce((sum, line) => sum + line.tax, 0));
    return { net, tax, total: money(net + tax) };
};
