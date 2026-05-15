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
                        className="overflow-hidden rounded-2xl border border-sand-200 bg-white"
                    >
                        <div className="border-b border-sand-200 bg-sand-50 px-4 py-3">
                            <h3 className="text-[11px] font-bold tracking-wider text-ink-500 uppercase">
                                {group.group || 'General'}
                            </h3>
                        </div>
                        <ul className="divide-y divide-sand-100">
                            {group.permissions.map((permission) => (
                                <li
                                    key={permission.id}
                                    className="flex items-center justify-between gap-4 px-4 py-3.5 transition hover:bg-teal-500/[0.02]"
                                >
                                    <div>
                                        <p className="text-sm font-semibold text-ink-900">
                                            {permission.name}
                                        </p>
                                        {permission.description && (
                                            <p className="mt-0.5 text-xs text-ink-300">
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
                                            className="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-[7px] border border-sand-200 bg-white hover:border-teal-400 hover:bg-teal-100"
                                            title="Edit"
                                        >
                                            <Pencil className="h-3.5 w-3.5 text-ink-500" />
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
