import PrimaryButton from '@/Components/PrimaryButton';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ roles, filters }) {
    const can = useCan();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.roles.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-gray-800">Roles</h2>
                    {can('roles.create') && (
                        <Link href={route('admin.roles.create')}>
                            <PrimaryButton>Add role</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Roles" />

            <form onSubmit={search} className="mb-4 flex gap-2">
                <input
                    name="search"
                    defaultValue={filters.search ?? ''}
                    placeholder="Search..."
                    className="rounded-md border-gray-300 shadow-sm"
                />
                <PrimaryButton type="submit">Search</PrimaryButton>
            </form>

            <div className="overflow-hidden rounded-lg bg-white shadow">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                                Name
                            </th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                                Description
                            </th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                                Permissions
                            </th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {roles.data.map((role) => (
                            <tr key={role.id}>
                                <td className="px-4 py-3 text-sm font-medium">
                                    {role.name}
                                    {role.is_system && (
                                        <span className="ms-2 text-xs text-gray-400">
                                            (system)
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-600">
                                    {role.description}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    {role.permissions_count}
                                </td>
                                <td className="space-x-2 px-4 py-3 text-right text-sm">
                                    {can('roles.update') && (
                                        <Link
                                            href={route('admin.roles.edit', role.id)}
                                            className="text-indigo-600 hover:underline"
                                        >
                                            Edit
                                        </Link>
                                    )}
                                    {can('roles.clone') && (
                                        <Link
                                            href={route('admin.roles.clone', role.id)}
                                            className="text-indigo-600 hover:underline"
                                        >
                                            Clone
                                        </Link>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
