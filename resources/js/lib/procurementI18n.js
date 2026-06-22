/**
 * Resolve procurement enum / config values to Title Case labels via i18n.
 */
export function titleCaseEnum(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return String(value)
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(' ');
}

export function paymentMethodLabel(t, method) {
    if (!method) {
        return '';
    }

    const key = `common.paymentMethods.${method}`;
    const label = t(key);

    return label === key ? titleCaseEnum(method) : label;
}

export function poStatusLabel(t, status) {
    if (!status) {
        return '';
    }

    const key = `pages.purchaseOrders.statuses.${status}`;
    const label = t(key);

    return label === key ? titleCaseEnum(status) : label;
}

export function matchStatusLabel(t, status) {
    if (!status) {
        return '';
    }

    const key = `pages.procurement.matchStatuses.${status}`;
    const label = t(key);

    return label === key ? titleCaseEnum(status) : label;
}

export function returnStatusLabel(t, status) {
    if (!status) {
        return '';
    }

    const key = `pages.procurement.returnStatuses.${status}`;
    const label = t(key);

    return label === key ? titleCaseEnum(status) : label;
}

export function invoiceStatusLabel(t, status) {
    if (!status) {
        return '';
    }

    const key = `pages.procurement.invoiceStatuses.${status}`;
    const label = t(key);

    return label === key ? titleCaseEnum(status) : label;
}

export function grnStatusLabel(t, status) {
    if (!status) {
        return '';
    }

    const key = `pages.goodsReceiving.grnStatuses.${status}`;
    const label = t(key);

    return label === key ? titleCaseEnum(status) : label;
}
