export function roundMoney(amount) {
    return Math.round(Number(amount) * 100) / 100;
}

export function formatPkr(amount) {
    return roundMoney(amount).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

export function lineGross(item) {
    return roundMoney(Number(item.unit_price) * Number(item.quantity));
}

export function computeItemLineTotal(item) {
    const gross = lineGross(item);

    if (!item.discount_type || item.discount_value == null || Number(item.discount_value) <= 0) {
        return gross;
    }

    const discountValue = Number(item.discount_value);
    let discountAmount = 0;

    if (item.discount_type === 'flat') {
        discountAmount = discountValue;
    } else if (item.discount_type === 'percent') {
        discountAmount = roundMoney(gross * (discountValue / 100));
    }

    return roundMoney(Math.max(0, gross - discountAmount));
}

export function lineTotal(item) {
    return computeItemLineTotal(item);
}

export function lineDiscount(item) {
    return roundMoney(lineGross(item) - lineTotal(item));
}

export function itemHasDiscount(item) {
    return lineDiscount(item) > 0;
}

export function computeCartTotals(items = []) {
    const lines = items.map((item) => ({
        gross: lineGross(item),
        total: lineTotal(item),
        discount: lineDiscount(item),
    }));

    const subtotal = roundMoney(lines.reduce((sum, line) => sum + line.gross, 0));
    const grandTotal = roundMoney(lines.reduce((sum, line) => sum + line.total, 0));
    const discount = roundMoney(lines.reduce((sum, line) => sum + line.discount, 0));

    return {
        subtotal,
        grandTotal,
        discount,
        itemCount: items.length,
    };
}
