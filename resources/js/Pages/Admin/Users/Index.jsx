import PrimaryButton from '@/Components/PrimaryButton';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ users, filters }) {
    const can = useCan();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.users.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-gray-800">Users</h2>
                    {can('users.create') && (
                        <Link href={route('admin.users.create')}>
                            <PrimaryButton>Add user</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Users" />

            <form onSubmit={search} className="mb-4 flex gap-2">
                <input
                    name="search"
                    defaultValue={filters.search ?? ''}
                    placeholder="Search name or email..."
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
                                Email
                            </th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                                Roles
                            </th>
                            <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">
                                Status
                            </th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {users.data.map((user) => (
                            <tr key={user.id}>
                                <td className="px-4 py-3 text-sm">{user.name}</td>
                                <td className="px-4 py-3 text-sm">{user.email}</td>
                                <td className="px-4 py-3 text-sm">
                                    {user.roles?.map((r) => r.name).join(', ')}
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <span
                                        className={
                                            user.is_active
                                                ? 'text-green-600'
                                                : 'text-red-600'
                                        }
                                    >
                                        {user.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    {can('users.update') && (
                                        <Link
                                            href={route('admin.users.edit', user.id)}
                                            className="text-indigo-600 hover:underline"
                                        >
                                            Edit
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
