import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const ROUTE_CRUMBS = {
    'Admin/Dashboard': ['home'],
    'Admin/Users/Index': ['home', 'users'],
    'Admin/Users/Create': ['home', 'users', 'create'],
    'Admin/Users/Edit': ['home', 'users', 'edit'],
    'Admin/Roles/Index': ['home', 'roles'],
    'Admin/Roles/Create': ['home', 'roles', 'create'],
    'Admin/Roles/Edit': ['home', 'roles', 'edit'],
    'Admin/Roles/Clone': ['home', 'roles', 'clone'],
    'Admin/Permissions/Index': ['home', 'permissions'],
    'Admin/Permissions/Create': ['home', 'permissions', 'create'],
    'Admin/Permissions/Edit': ['home', 'permissions', 'edit'],
};

const CRUMB_HREFS = {
    home: 'admin.dashboard',
    users: 'admin.users.index',
    roles: 'admin.roles.index',
    permissions: 'admin.permissions.index',
};

export function useBreadcrumbs() {
    const { component, props } = usePage();
    const { t } = useTranslation();

    return useMemo(() => {
        if (props.breadcrumbs?.length) {
            return props.breadcrumbs;
        }

        const keys = ROUTE_CRUMBS[component] ?? ['home'];

        return keys.map((key, index) => ({
            label: t(`breadcrumbs.${key}`),
            href:
                index < keys.length - 1 && CRUMB_HREFS[key]
                    ? route(CRUMB_HREFS[key])
                    : undefined,
        }));
    }, [component, props.breadcrumbs, t]);
}
