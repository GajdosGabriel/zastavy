export function adjustmentAmount(subtotal, adjustment, coupon = 0) {
    if (!adjustment) return 0;
    const cents = value => Math.round((Number(value) + Number.EPSILON) * 100);
    const subtotalCents = cents(subtotal);
    const value = cents(adjustment.value || 0);
    const amount = adjustment.type === 'percent'
        ? Math.round(subtotalCents * value / 10000)
        : value;
    return (adjustment.direction === 'discount'
        ? -Math.min(Math.max(0, subtotalCents - cents(coupon)), amount)
        : amount) / 100 || 0;
}
