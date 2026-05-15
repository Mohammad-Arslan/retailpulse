import { Building2, KeyRound, LayoutDashboard, Shield, Users } from 'lucide-react';

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
