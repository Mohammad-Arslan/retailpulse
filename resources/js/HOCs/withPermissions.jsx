import { useCan } from '@/Hooks/useCan';
import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

export function withPermissions(permission) {
    return function wrap(Page) {
        function PermissionGate(props) {
            const can = useCan();
            const user = usePage().props.auth?.user;

            useEffect(() => {
                if (!user) {
                    router.visit(route('login'));
                    return;
                }

                if (!can(permission)) {
                    router.visit(route('admin.dashboard'));
                }
            }, [can, user]);

            if (!user || !can(permission)) {
                return null;
            }

            return <Page {...props} />;
        }

        PermissionGate.displayName = `withPermissions(${Page.displayName ?? Page.name ?? 'Page'})`;

        return PermissionGate;
    };
}
