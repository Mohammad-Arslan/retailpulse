import {
    Building2,
    FolderTree,
    KeyRound,
    LayoutDashboard,
    Package,
    Shield,
    Tag,
    Users,
} from 'lucide-react';

export const ADMIN_NAV_SECTIONS = [
    {
        label: 'Overview',
        labelKey: 'overview',
        items: [
            {
                label: 'Dashboard',
                labelKey: 'dashboard',
                href: 'admin.dashboard',
                routeName: 'admin.dashboard',
                permission: 'admin.dashboard.view',
                icon: LayoutDashboard,
                keywords: ['home', 'overview', 'stats'],
            },
        ],
    },
    {
        label: 'Organization',
        labelKey: 'organization',
        items: [
            {
                label: 'Branches',
                labelKey: 'branches',
                href: 'admin.branches.index',
                routeName: 'admin.branches.*',
                permission: 'branches.view',
                icon: Building2,
                keywords: ['stores', 'locations', 'outlets'],
            },
        ],
    },
    {
        label: 'Catalog',
        labelKey: 'catalog',
        items: [
            {
                label: 'Products',
                labelKey: 'products',
                href: 'admin.products.index',
                routeName: 'admin.products.*',
                permission: 'products.view',
                icon: Package,
                keywords: ['items', 'sku', 'inventory', 'pim'],
            },
            {
                label: 'Categories',
                labelKey: 'categories',
                href: 'admin.categories.index',
                routeName: 'admin.categories.*',
                permission: 'products.view',
                icon: FolderTree,
                keywords: ['taxonomy', 'groups'],
            },
            {
                label: 'Brands',
                labelKey: 'brands',
                href: 'admin.brands.index',
                routeName: 'admin.brands.*',
                permission: 'products.view',
                icon: Tag,
                keywords: ['manufacturer', 'vendor'],
            },
        ],
    },
    {
        label: 'Admin',
        labelKey: 'admin',
        items: [
            {
                label: 'Users',
                labelKey: 'users',
                href: 'admin.users.index',
                routeName: 'admin.users.*',
                permission: 'users.view',
                icon: Users,
                keywords: ['team', 'staff', 'members'],
            },
            {
                label: 'Roles',
                labelKey: 'roles',
                href: 'admin.roles.index',
                routeName: 'admin.roles.*',
                permission: 'roles.view',
                icon: Shield,
                keywords: ['access', 'profiles'],
            },
            {
                label: 'Permissions',
                labelKey: 'permissions',
                href: 'admin.permissions.index',
                routeName: 'admin.permissions.*',
                permission: 'permissions.view',
                icon: KeyRound,
                keywords: ['capabilities', 'acl'],
            },
        ],
    },
];
