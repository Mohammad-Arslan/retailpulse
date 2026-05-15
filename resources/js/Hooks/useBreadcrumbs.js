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
    'Admin/Branches/Index': ['home', 'branches'],
    'Admin/Branches/Create': ['home', 'branches', 'create'],
    'Admin/Branches/Edit': ['home', 'branches', 'edit'],
    'Admin/Categories/Index': ['home', 'categories'],
    'Admin/Categories/Create': ['home', 'categories', 'create'],
    'Admin/Categories/Edit': ['home', 'categories', 'edit'],
    'Admin/Brands/Index': ['home', 'brands'],
    'Admin/Brands/Create': ['home', 'brands', 'create'],
    'Admin/Brands/Edit': ['home', 'brands', 'edit'],
    'Admin/Products/Index': ['home', 'products'],
    'Admin/Products/Create': ['home', 'products', 'create'],
    'Admin/Products/Edit': ['home', 'products', 'edit'],
};

const CRUMB_HREFS = {
    home: 'admin.dashboard',
    users: 'admin.users.index',
    roles: 'admin.roles.index',
    permissions: 'admin.permissions.index',
    branches: 'admin.branches.index',
    categories: 'admin.categories.index',
    brands: 'admin.brands.index',
    products: 'admin.products.index',
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
