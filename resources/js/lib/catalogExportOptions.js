/**
 * Build export options payload for the import/export API.
 *
 * Supports:
 * - filters only (toolbar export)
 * - selected ids only (bulk export without list filters)
 * - filters + ids together (intersection)
 *
 * @param {Record<string, unknown>} exportOptions
 * @param {number[]} selectedIds
 * @returns {Record<string, unknown>}
 */
export function buildCatalogExportOptions(exportOptions = {}, selectedIds = []) {
    const filters = { ...(exportOptions.filters ?? {}) };

    if (selectedIds.length > 0) {
        filters.ids = selectedIds;
    }

    const cleanFilters = Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== undefined && value !== null && value !== ''),
    );

    const payload = { ...exportOptions };

    if (Object.keys(cleanFilters).length > 0) {
        payload.filters = cleanFilters;
    } else {
        delete payload.filters;
    }

    return payload;
}
