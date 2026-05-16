import axios from 'axios';

function client() {
    const instance = axios.create({
        withCredentials: true,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (token) {
        instance.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }

    return instance;
}

export async function uploadImport(entityType, file, mode) {
    const form = new FormData();
    form.append('entity_type', entityType);
    form.append('file', file);
    form.append('mode', mode);

    const { data } = await client().post(route('admin.import-export.imports.upload'), form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return data;
}

export async function saveMapping(ulid, mapping) {
    const { data } = await client().post(route('admin.import-export.imports.mapping', ulid), {
        mapping,
    });

    return data;
}

export async function fetchRules(ulid) {
    const { data } = await client().get(route('admin.import-export.imports.rules', ulid));

    return data;
}

export async function saveRules(ulid, payload) {
    const { data } = await client().post(route('admin.import-export.imports.rules.save', ulid), payload);

    return data;
}

export async function confirmImport(ulid, payload) {
    const { data } = await client().post(route('admin.import-export.imports.confirm', ulid), payload);

    return data;
}

export async function initiateExport(entityType, options = {}) {
    const { data } = await client().post(route('admin.import-export.exports.initiate'), {
        entity_type: entityType,
        options,
    });

    return data;
}

export async function fetchJobs() {
    const { data } = await client().get(route('admin.import-export.jobs.index'));

    return data.jobs ?? [];
}

export async function fetchJob(ulid) {
    const { data } = await client().get(route('admin.import-export.jobs.show', ulid));

    return data.job;
}

export async function cancelJob(ulid) {
    const { data } = await client().post(route('admin.import-export.jobs.cancel', ulid));

    return data.job;
}

/** Relative URLs avoid mixed-content warnings when APP_URL differs from the browser host. */
export function templateDownloadUrl(entityType) {
    return route('admin.import-export.templates.download', entityType, false);
}

export function jobDownloadUrl(ulid) {
    return route('admin.import-export.jobs.download', ulid, false);
}

export function jobErrorsUrl(ulid) {
    return route('admin.import-export.errors', ulid, false);
}
