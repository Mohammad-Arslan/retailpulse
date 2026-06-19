/**
 * On edit, simple/combo products expose default_* pricing fields but the API
 * persists prices through the variants array.
 *
 * @param {Record<string, unknown>} formData
 */
export function mergeDefaultPricingIntoVariants(formData) {
    const type = formData.type;
    const usesDefaultPricing = type !== 'variable';

    if (!usesDefaultPricing || !Array.isArray(formData.variants) || formData.variants.length === 0) {
        return formData;
    }

    const variants = [...formData.variants];
    variants[0] = {
        ...variants[0],
        cost_price: formData.default_cost_price ?? variants[0].cost_price,
        sell_price: formData.default_sell_price ?? variants[0].sell_price,
        reorder_point:
            formData.default_reorder_point !== undefined && formData.default_reorder_point !== null
                ? formData.default_reorder_point
                : variants[0].reorder_point,
        preferred_supplier_id:
            formData.default_preferred_supplier_id !== undefined &&
            formData.default_preferred_supplier_id !== ''
                ? formData.default_preferred_supplier_id
                : variants[0].preferred_supplier_id ?? null,
        alternate_supplier_ids:
            formData.default_alternate_supplier_ids ?? variants[0].alternate_supplier_ids ?? [],
    };

    return { ...formData, variants };
}
