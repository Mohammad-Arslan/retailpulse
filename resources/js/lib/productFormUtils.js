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
    };

    return { ...formData, variants };
}
