import PageHeader from '@/Components/common/PageHeader';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Copy, Pencil, Plus, Search } from 'lucide-react';

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
        <AdminLayout>
            <Head title="Roles" />

            <PageHeader
                title="Roles"
                description="Define access profiles and assign permissions to each role."
            >
                {can('roles.create') && (
                    <Link href={route('admin.roles.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        Add Role
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar">
                <div className="rp-search-inset">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder="Search roles..."
                        className="rp-search-input"
                    />
                </div>
                <button type="submit" className="rp-btn-outline">
                    Search
                </button>
            </form>

            <div className="rp-user-table-wrap">
                <div className="overflow-x-auto">
                    <table className="w-full border-collapse">
                        <thead>
                            <tr>
                                <th className="px-4 py-3 text-left text-[11px] font-bold tracking-wider text-ink-300 uppercase">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-bold tracking-wider text-ink-300 uppercase">
                                    Description
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-bold tracking-wider text-ink-300 uppercase">
                                    Permissions
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {roles.data.map((role) => (
                                <tr
                                    key={role.id}
                                    className="group border-b border-sand-100 last:border-0 hover:bg-teal-500/[0.02]"
                                >
                                    <td className="px-4 py-3">
                                        <span className="text-sm font-semibold text-rp-text">
                                            {role.name}
                                        </span>
                                        {role.is_system && (
                                            <span className="ms-2 text-[11px] text-rp-text-muted">
                                                (system)
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-rp-text-secondary">
                                        {role.description || '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="inline-flex rounded-full bg-teal-100 px-2.5 py-0.5 text-xs font-semibold text-teal-500">
                                            {role.permissions_count}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1.5">
                                            {can('roles.update') && (
                                                <Link
                                                    href={route(
                                                        'admin.roles.edit',
                                                        role.id,
                                                    )}
                                                    className="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] border border-sand-200 bg-white hover:border-teal-400 hover:bg-teal-100"
                                                    title="Edit"
                                                >
                                                    <Pencil className="h-3.5 w-3.5 text-ink-500" />
                                                </Link>
                                            )}
                                            {can('roles.clone') && (
                                                <Link
                                                    href={route(
                                                        'admin.roles.clone',
                                                        role.id,
                                                    )}
                                                    className="flex h-[30px] w-[30px] items-center justify-center rounded-[7px] border border-sand-200 bg-white hover:border-teal-400 hover:bg-teal-100"
                                                    title="Clone"
                                                >
                                                    <Copy className="h-3.5 w-3.5 text-ink-500" />
                                                </Link>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
