import axios from 'axios';
import { applyCsrfHeaders, readCsrfToken, syncCsrfToken } from '@/lib/csrf';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.defaults.withCredentials = true;

syncCsrfToken(readCsrfToken());

window.axios.interceptors.request.use((config) => {
    applyCsrfHeaders(config.headers);

    return config;
});
