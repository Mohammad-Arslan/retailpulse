import { usePage } from '@inertiajs/react';

export function useCan() {
    const permissions = usePage().props.auth?.permissions ?? [];

    return (permission) => permissions.includes(permission);
}
