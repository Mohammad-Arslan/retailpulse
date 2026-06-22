/**
 * Format a monetary amount with the given ISO 4217 currency code.
 * Falls back to "1,234.56 CODE" when the currency is unknown to Intl.
 */
export function formatCurrency(amount, currencyCode = 'USD', locale) {
    const value = Number(amount) || 0;
    const code = (currencyCode || 'USD').toUpperCase();

    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: code,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
    } catch {
        return `${value.toLocaleString(locale, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })} ${code}`;
    }
}
