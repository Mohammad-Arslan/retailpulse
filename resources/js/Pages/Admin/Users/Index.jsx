import PageHeader from '@/Components/common/PageHeader';
import RolePill from '@/Components/common/RolePill';
import UserAvatar from '@/Components/common/UserAvatar';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';

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
        <AdminLayout>
            <Head title="Users" />

            <PageHeader
                title="Team Members"
                description="Manage users, roles, and access permissions across all branches."
            >
                {can('users.create') && (
                    <Link href={route('admin.users.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        Add User
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar">
                <div className="rp-search-inset">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder="Search by name or email..."
                        className="rp-search-input"
                    />
                </div>
                <button type="submit" className="rp-btn-outline">
                    Search
                </button>
            </form>

            <div className="rp-user-table-wrap">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[720px] border-collapse">
                        <thead>
                            <tr>
                                <th className="rp-table-head rp-table-head-bg px-4 py-3">
                                    User
                                </th>
                                <th className="rp-table-head rp-table-head-bg px-4 py-3">
                                    Roles
                                </th>
                                <th className="rp-table-head rp-table-head-bg px-4 py-3">
                                    Status
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-12 text-center text-sm text-rp-text-muted"
                                    >
                                        No users found.
                                    </td>
                                </tr>
                            ) : (
                                users.data.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="group border-b border-sand-100 last:border-0 hover:bg-teal-500/[0.02]"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <UserAvatar name={user.name} />
                                                <div>
                                                    <div className="text-sm font-semibold text-rp-text">
                                                        {user.name}
                                                    </div>
                                                    <div className="text-xs text-rp-text-muted">
                                                        {user.email}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1.5">
                                                {user.roles?.length ? (
                                                    user.roles.map((role) => (
                                                        <RolePill
                                                            key={role.id ?? role.name}
                                                            name={role.name}
                                                        />
                                                    ))
                                                ) : (
                                                    <span className="text-xs text-ink-300">
                                                        —
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-1.5 text-xs text-rp-text-secondary">
                                                <span
                                                    className={`h-1.5 w-1.5 rounded-full ${user.is_active ? 'bg-teal-400 shadow-[0_0_0_2px] shadow-teal-100' : 'bg-ink-300'}`}
                                                />
                                                {user.is_active ? 'Active' : 'Inactive'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1.5 opacity-100 transition group-hover:opacity-100 lg:opacity-0 lg:group-hover:opacity-100">
                                                {can('users.update') && (
                                                    <Link
                                                        href={route(
                                                            'admin.users.edit',
                                                            user.id,
                                                        )}
                                                        className="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] border border-sand-200 bg-white transition hover:border-teal-400 hover:bg-teal-100"
                                                        title="Edit"
                                                    >
                                                        <Pencil className="h-3.5 w-3.5 text-ink-500" />
                                                    </Link>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {users.last_page > 1 && (
                    <div className="flex flex-col gap-3 border-t border-rp-border bg-rp-surface-inset px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                        <span className="text-[13px] text-rp-text-secondary">
                            Showing{' '}
                            <strong className="text-rp-text">
                                {users.from}–{users.to}
                            </strong>{' '}
                            of{' '}
                            <strong className="text-rp-text">
                                {users.total}
                            </strong>{' '}
                            users
                        </span>
                        <div className="flex flex-wrap gap-1.5">
                            {users.links?.map((link, i) =>
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        preserveState
                                        className={`flex h-8 min-w-8 items-center justify-center rounded-[7px] border px-2 text-[13px] font-medium transition ${
                                            link.active
                                                ? 'border-ink-900 bg-ink-900 text-white'
                                                : 'border-sand-200 bg-white text-ink-700 hover:border-ink-900 hover:bg-ink-900 hover:text-white'
                                        }`}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        className="flex h-8 min-w-8 items-center justify-center rounded-[7px] border border-sand-200 bg-white px-2 text-[13px] text-ink-300"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ),
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
