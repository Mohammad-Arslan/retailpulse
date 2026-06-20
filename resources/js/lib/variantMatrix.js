/**
 * Client-side mirror of App\Support\VariantMatrix for product create flows.
 */

/**
 * @param {Record<string, string>} attributes
 */
export function attributeKey(attributes) {
    const sorted = Object.keys(attributes ?? {})
        .sort()
        .reduce((acc, key) => {
            acc[key] = attributes[key];
            return acc;
        }, {});

    return JSON.stringify(sorted);
}

/**
 * @param {Array<{ name: string, options: string[] }>} attributeSets
 * @returns {Array<Record<string, string>>}
 */
export function variantCombinations(attributeSets) {
    const sets = (attributeSets ?? []).filter(
        (set) => String(set?.name ?? '').trim() !== '' && Array.isArray(set?.options),
    );

    if (sets.length === 0) {
        return [];
    }

    let result = [[]];

    for (const set of sets) {
        const options = [...new Set(set.options.map((option) => String(option).trim()).filter(Boolean))];

        if (options.length === 0) {
            continue;
        }

        const next = [];

        for (const combo of result) {
            for (const option of options) {
                next.push({ ...combo, [set.name]: option });
            }
        }

        result = next;
    }

    return result.length === 0 ? [] : result;
}

/**
 * @param {Record<string, string>} attributes
 */
export function variantLabel(attributes) {
    const values = Object.values(attributes ?? {});

    return values.length > 0 ? values.join(' / ') : '';
}

/**
 * @param {Array<{ name: string, options: string[] }>} attributeSets
 * @param {Array<Record<string, unknown>>} existingVariants
 * @param {{ cost_price?: string, sell_price?: string, reorder_point?: string }} defaults
 */
export function buildVariantRows(attributeSets, existingVariants = [], defaults = {}) {
    const combinations = variantCombinations(attributeSets);

    const existingByKey = new Map(
        (existingVariants ?? [])
            .filter((variant) => variant?.attributes && typeof variant.attributes === 'object')
            .map((variant) => [attributeKey(variant.attributes), variant]),
    );

    return combinations.map((attributes) => {
        const existing = existingByKey.get(attributeKey(attributes));

        return {
            name: existing?.name ?? variantLabel(attributes),
            attributes,
            cost_price: existing?.cost_price ?? defaults.cost_price ?? '0',
            sell_price: existing?.sell_price ?? defaults.sell_price ?? '0',
            reorder_point: existing?.reorder_point ?? defaults.reorder_point ?? '',
            preferred_supplier_id: existing?.preferred_supplier_id ?? null,
            alternate_supplier_ids: existing?.alternate_supplier_ids ?? [],
        };
    });
}
