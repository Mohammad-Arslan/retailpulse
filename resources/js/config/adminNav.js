import { KeyRound, LayoutDashboard, Shield, Users } from 'lucide-react';

export const ADMIN_NAV_SECTIONS = [
    {
        label: 'Overview',
        items: [
            {
                label: 'Dashboard',
                href: 'admin.dashboard',
                routeName: 'admin.dashboard',
                permission: 'admin.dashboard.view',
                icon: LayoutDashboard,
                keywords: ['home', 'overview', 'stats'],
            },
        ],
    },
    {
        label: 'Admin',
        items: [
            {
                label: 'Users',
                href: 'admin.users.index',
                routeName: 'admin.users.*',
                permission: 'users.view',
                icon: Users,
                keywords: ['team', 'staff', 'members'],
            },
            {
                label: 'Roles',
                href: 'admin.roles.index',
                routeName: 'admin.roles.*',
                permission: 'roles.view',
                icon: Shield,
                keywords: ['access', 'profiles'],
            },
            {
                label: 'Permissions',
                href: 'admin.permissions.index',
                routeName: 'admin.permissions.*',
                permission: 'permissions.view',
                icon: KeyRound,
                keywords: ['capabilities', 'acl'],
            },
        ],
    },
];
