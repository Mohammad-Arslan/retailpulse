import { ensureCsrfCookie } from '@/lib/csrf';

/** Relative URLs so session cookies and CSRF work when APP_URL differs from the browser host. */
function apiRoute(name, params = undefined) {
    return route(name, params, false);
}

function client() {
    return window.axios;
}

async function post(url, body) {
    await ensureCsrfCookie();

    const { data } = await client().post(url, body);

    return data;
}

async function get(url) {
    await ensureCsrfCookie();

    const { data } = await client().get(url);

    return data;
}

export async function uploadImport(entityType, file, mode) {
    const form = new FormData();
    form.append('entity_type', entityType);
    form.append('file', file);
    form.append('mode', mode);

    return post(apiRoute('admin.import-export.imports.upload'), form);
}

export async function saveMapping(ulid, mapping) {
    return post(apiRoute('admin.import-export.imports.mapping', ulid), {
        mapping,
    });
}

export async function fetchRules(ulid) {
    return get(apiRoute('admin.import-export.imports.rules', ulid));
}

export async function saveRules(ulid, payload) {
    return post(apiRoute('admin.import-export.imports.rules.save', ulid), payload);
}

export async function confirmImport(ulid, payload) {
    return post(apiRoute('admin.import-export.imports.confirm', ulid), payload);
}

export async function initiateExport(entityType, options = {}) {
    return post(apiRoute('admin.import-export.exports.initiate'), {
        entity_type: entityType,
        options,
    });
}

export async function fetchJobs() {
    const data = await get(apiRoute('admin.import-export.jobs.index'));

    return data.jobs ?? [];
}

export async function fetchJob(ulid) {
    const data = await get(apiRoute('admin.import-export.jobs.show', ulid));

    return data.job;
}

export async function fetchJobRowErrors(ulid, { search = '', page = 1, perPage = 50 } = {}) {
    const params = new URLSearchParams();

    if (search) {
        params.set('search', search);
    }

    params.set('page', String(page));
    params.set('per_page', String(perPage));

    const query = params.toString();
    const url = `${apiRoute('admin.import-export.jobs.row-errors', ulid)}?${query}`;

    return get(url);
}

export async function cancelJob(ulid) {
    const data = await post(apiRoute('admin.import-export.jobs.cancel', ulid));

    return data.job;
}

export function templateDownloadUrl(entityType) {
    return apiRoute('admin.import-export.templates.download', entityType);
}

export function jobDownloadUrl(ulid) {
    return apiRoute('admin.import-export.jobs.download', ulid);
}

export function jobErrorsUrl(ulid) {
    return apiRoute('admin.import-export.errors', ulid);
}
