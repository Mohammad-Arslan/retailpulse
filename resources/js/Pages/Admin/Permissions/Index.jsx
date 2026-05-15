import PrimaryButton from '@/Components/PrimaryButton';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ permissionGroups }) {
    const can = useCan();

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-gray-800">
                        Permissions
                    </h2>
                    {can('permissions.create') && (
                        <Link href={route('admin.permissions.create')}>
                            <PrimaryButton>Add permission</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Permissions" />

            <div className="space-y-6">
                {permissionGroups.map((group) => (
                    <div
                        key={group.group}
                        className="overflow-hidden rounded-lg bg-white shadow"
                    >
                        <div className="border-b border-gray-200 bg-gray-50 px-4 py-3">
                            <h3 className="text-sm font-semibold uppercase text-gray-600">
                                {group.group}
                            </h3>
                        </div>
                        <ul className="divide-y divide-gray-200">
                            {group.permissions.map((permission) => (
                                <li
                                    key={permission.id}
                                    className="flex items-center justify-between px-4 py-3"
                                >
                                    <div>
                                        <p className="text-sm font-medium">
                                            {permission.name}
                                        </p>
                                        {permission.description && (
                                            <p className="text-xs text-gray-500">
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
                                            className="text-sm text-indigo-600 hover:underline"
                                        >
                                            Edit
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
