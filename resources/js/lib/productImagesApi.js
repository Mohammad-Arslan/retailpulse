import { ensureCsrfCookie } from '@/lib/csrf';

function apiRoute(name, params = undefined) {
    return route(name, params, false);
}

/**
 * @param {number} productId
 * @param {{ images?: File[], removeImageIds?: number[] }} payload
 */
export async function syncProductImages(productId, { images = [], removeImageIds = [] } = {}) {
    await ensureCsrfCookie();

    const form = new FormData();

    images.forEach((file) => {
        form.append('images[]', file);
    });

    removeImageIds.forEach((id) => {
        form.append('remove_image_ids[]', String(id));
    });

    await window.axios.post(apiRoute('admin.products.images.sync', productId), form, {
        headers: { Accept: 'application/json' },
    });
}
