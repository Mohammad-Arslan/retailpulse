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

async function del(url) {
    await ensureCsrfCookie();
    const { data } = await window.axios.delete(url);
    return data;
}

export const checkoutApi = {
    bootstrap: (cartId) => get(r('api.v1.checkout.show', { cartId })),
    confirm: (cartId, payload = {}) =>
        post(r('api.v1.checkout.confirm', { cartId }), payload),
    abandon: (cartId) => post(r('api.v1.checkout.abandon', { cartId })),
};

export const loyaltyApi = {
    redemptionOptions: (customerId) =>
        get(r('api.v1.customers.loyalty.redemption-options', { customer: customerId })),
    redeem: (customerId, payload) =>
        post(r('api.v1.customers.loyalty.redeem', { customer: customerId }), payload),
};

export const saleApi = {
    get: (id) => get(r('api.v1.sales.show', { id })),
    addPayment: (id, payload) => post(r('api.v1.sales.payments.store', { id }), payload),
    removePayment: (id, paymentId) =>
        del(r('api.v1.sales.payments.destroy', { id, paymentId })),
    void: (id) => post(r('api.v1.sales.void', { id })),
    invoice: (id) => get(r('api.v1.sales.invoice', { id })),
    generatePdf: (id) => post(r('api.v1.sales.invoice.pdf', { id })),
    share: (id, method) => post(r('api.v1.sales.invoice.share', { id }), { method }),
};

export const customerApi = {
    search: (q) => get(r('api.v1.customers.search'), { q }),
    profile: (id) => get(r('api.v1.customers.show', { id })),
    topUpWallet: (id, payload) =>
        post(r('api.v1.customers.wallet-top-up', { customer: id }), payload),
};
