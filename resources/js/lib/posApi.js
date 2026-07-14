import { ensureCsrfCookie } from '@/lib/csrf';

function r(name, params = undefined) {
    return route(name, params, false);
}

async function get(url, params = {}) {
    await ensureCsrfCookie();
    const { data } = await window.axios.get(url, { params });
    return data;
}

async function post(url, body = {}) {
    await ensureCsrfCookie();
    const { data } = await window.axios.post(url, body);
    return data;
}

async function patch(url, body = {}) {
    await ensureCsrfCookie();
    const { data } = await window.axios.patch(url, body);
    return data;
}

async function del(url) {
    await ensureCsrfCookie();
    await window.axios.delete(url);
}

// PIN
export const pinApi = {
    status: () => get(r('api.v1.pos.pin.status')),
    verify: (pin) => post(r('api.v1.pos.pin.verify'), { pin }),
    setPin: (pin, pin_confirmation) =>
        post(r('api.v1.pos.pin.set'), { pin, pin_confirmation }),
    resetLockout: (userId) =>
        post(r('api.v1.pos.pin.reset', { userId })),
};

// Product search
export const searchApi = {
    search: (q, branchId) =>
        get(r('api.v1.pos.products.search'), { q, branch_id: branchId }),
    catalog: (params) => get(r('api.v1.pos.products.catalog'), params),
    filters: (branchId) =>
        get(r('api.v1.pos.products.filters'), { branch_id: branchId }),
};

// Carts
export const cartApi = {
    list: () => get(r('api.v1.pos.carts.index')),
    create: (branchId) => post(r('api.v1.pos.carts.store'), { branch_id: branchId }),
    get: (cartId) => get(r('api.v1.pos.carts.show', { cartId })),
    suspend: (cartId) => patch(r('api.v1.pos.carts.suspend', { cartId })),
    resume: (cartId) => patch(r('api.v1.pos.carts.resume', { cartId })),
    void: (cartId) => patch(r('api.v1.pos.carts.void', { cartId })),
    complete: (cartId) => patch(r('api.v1.pos.carts.complete', { cartId })),
    reopen: (cartId) => patch(r('api.v1.pos.carts.reopen', { cartId })),
    checkout: (cartId) => post(r('api.v1.pos.carts.checkout', { cartId })),
    stockWarnings: (cartId) =>
        get(r('api.v1.pos.carts.stock-warnings', { cartId })),
};

// Cart items
export const cartItemApi = {
    add: (cartId, payload) =>
        post(r('api.v1.pos.cart-items.store', { cartId }), payload),
    update: (cartId, itemId, payload) =>
        patch(r('api.v1.pos.cart-items.update', { cartId, itemId }), payload),
    remove: (cartId, itemId) =>
        del(r('api.v1.pos.cart-items.destroy', { cartId, itemId })),
};
