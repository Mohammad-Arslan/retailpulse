import PageHeader from '@/Components/common/PageHeader';
import { useCan } from '@/Hooks/useCan';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function matchesQuery(permission, group, query) {
    if (!query) {
        return true;
    }

    const haystack = [
        permission.display_name,
        permission.name,
        permission.description,
        group.group_label,
        group.group,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return haystack.includes(query);
}

function Index({ permissionGroups = [] }) {
    const can = useCan();
    const { t } = useTranslation();
    const [search, setSearch] = useState('');

    const query = search.trim().toLowerCase();

    const filteredGroups = useMemo(() => {
        if (!query) {
            return permissionGroups;
        }

        return permissionGroups
            .map((group) => ({
                ...group,
                permissions: group.permissions.filter((permission) =>
                    matchesQuery(permission, group, query),
                ),
            }))
            .filter((group) => group.permissions.length > 0);
    }, [permissionGroups, query]);

    return (
        <>
            <Head title={t('pages.permissions.title')} />

            <PageHeader
                title={t('pages.permissions.title')}
                description={t('pages.permissions.description')}
            >
                {can('permissions.create') && (
                    <Link
                        href={route('admin.permissions.create')}
                        className="rp-btn-primary"
                    >
                        <Plus className="h-4 w-4" />
                        {t('common.addPermission')}
                    </Link>
                )}
            </PageHeader>

            <div className="rp-filter-bar mb-5">
                <div className="rp-search-inset flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('pages.permissions.searchPlaceholder')}
                        className="rp-search-input"
                        aria-label={t('common.search')}
                    />
                </div>
            </div>

            <div className="space-y-5">
                {filteredGroups.length === 0 ? (
                    <div className="rounded-2xl border border-rp-border bg-rp-surface px-4 py-10 text-center text-sm text-rp-text-muted">
                        {t('pages.permissions.emptySearch')}
                    </div>
                ) : (
                    filteredGroups.map((group) => (
                        <div
                            key={group.group}
                            className="overflow-hidden rounded-2xl border border-rp-border bg-rp-surface"
                        >
                            <div className="border-b border-rp-border bg-rp-surface-inset px-4 py-3">
                                <h3 className="text-sm font-semibold text-rp-text">
                                    {group.group_label || group.group || t('pages.permissions.generalGroup')}
                                </h3>
                            </div>
                            <ul className="divide-y divide-rp-border-subtle">
                                {group.permissions.map((permission) => (
                                    <li
                                        key={permission.id}
                                        className="flex items-center justify-between gap-4 px-4 py-3.5 transition hover:bg-teal-500/[0.06]"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-rp-text">
                                                {permission.display_name || permission.name}
                                            </p>
                                            <p className="mt-0.5 font-mono text-[11px] text-rp-text-muted">
                                                {permission.name}
                                            </p>
                                            {permission.description && (
                                                <p className="mt-1 text-xs text-rp-text-secondary">
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
                                                title={t('common.edit')}
                                            >
                                                <Pencil className="h-3.5 w-3.5 text-rp-text-secondary" />
                                            </Link>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))
                )}
            </div>
        </>
    );
}

export default withAdminLayout(Index);
