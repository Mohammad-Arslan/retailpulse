/**
 * Resolve warehouse type enum values to Title Case labels via i18n.
 */
export function warehouseTypeLabel(t, type) {
    if (!type) {
        return '';
    }

    const key = `pages.warehouses.types.${type}`;
    const label = t(key);

    return label === key ? type : label;
}

export function warehouseTypeOptions(t, types = []) {
    return types.map((value) => ({
        value,
        label: warehouseTypeLabel(t, value),
    }));
}
