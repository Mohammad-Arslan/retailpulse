import PageHeader from '@/Components/common/PageHeader';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';

export default function Index({ permissionGroups }) {
    const can = useCan();

    return (
        <AdminLayout>
            <Head title="Permissions" />

            <PageHeader
                title="Permissions"
                description="Granular capabilities assigned to roles across the system."
            >
                {can('permissions.create') && (
                    <Link
                        href={route('admin.permissions.create')}
                        className="rp-btn-primary"
                    >
                        <Plus className="h-4 w-4" />
                        Add Permission
                    </Link>
                )}
            </PageHeader>

            <div className="space-y-5">
                {permissionGroups.map((group) => (
                    <div
                        key={group.group}
                        className="overflow-hidden rounded-2xl border border-rp-border bg-rp-surface"
                    >
                        <div className="border-b border-rp-border bg-rp-surface-inset px-4 py-3">
                            <h3 className="text-[11px] font-bold tracking-wider text-rp-text-secondary uppercase">
                                {group.group || 'General'}
                            </h3>
                        </div>
                        <ul className="divide-y divide-rp-border-subtle">
                            {group.permissions.map((permission) => (
                                <li
                                    key={permission.id}
                                    className="flex items-center justify-between gap-4 px-4 py-3.5 transition hover:bg-teal-500/[0.06]"
                                >
                                    <div>
                                        <p className="text-sm font-semibold text-rp-text">
                                            {permission.name}
                                        </p>
                                        {permission.description && (
                                            <p className="mt-0.5 text-xs text-rp-text-muted">
                                                {permission.description}
                                            </p>
                                        )}
                                    </div>
                                    {can('permissions.update') && (
                                        <Link
                                            href={route(
                                                'admin.permissions.edit',
                                                permission.id,
                                            )}
                                            className="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-[7px] border border-rp-border bg-rp-surface hover:border-teal-400 hover:bg-teal-500/15"
                                            title="Edit"
                                        >
                                            <Pencil className="h-3.5 w-3.5 text-rp-text-secondary" />
                                        </Link>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
