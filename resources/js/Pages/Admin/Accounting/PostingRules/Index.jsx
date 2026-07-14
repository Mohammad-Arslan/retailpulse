import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Copy, FileSpreadsheet, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ ruleSets, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.accounting.posting-rules.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'code',
                header: t('pages.accounting.postingRules.columns.code'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <FileSpreadsheet className="h-4 w-4" />
                        </span>
                        <div>
                            <Link
                                href={route('admin.accounting.posting-rules.edit', row.original.id)}
                                className="text-sm font-semibold text-teal-600 hover:underline"
                            >
                                {row.original.code}
                            </Link>
                            <div className="text-xs text-rp-text-muted">{row.original.name}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'event_type',
                header: t('pages.accounting.postingRules.columns.eventType'),
                cell: ({ row }) => (
                    <span className="font-mono text-xs">{row.original.event_type}</span>
                ),
            },
            {
                id: 'lines_count',
                header: t('pages.accounting.postingRules.columns.lines'),
                cell: ({ row }) => row.original.lines_count ?? 0,
            },
            {
                id: 'priority',
                header: t('pages.accounting.postingRules.columns.priority'),
                cell: ({ row }) => row.original.priority ?? '—',
            },
            {
                id: 'effective_from',
                header: t('pages.accounting.postingRules.columns.effectiveFrom'),
                cell: ({ row }) => row.original.effective_from?.slice(0, 10) ?? '—',
            },
            {
                id: 'status',
                header: t('common.status'),
                cell: ({ row }) => (
                    <span
                        className={`text-xs font-medium ${row.original.status === 'active' ? 'text-teal-600' : 'text-rp-text-muted'}`}
                    >
                        {row.original.status === 'active' ? t('common.active') : t('common.inactive')}
                    </span>
                ),
            },
        ],
        [t],
    );

    const rowActions = (ruleSet) => {
        const actions = [
            {
                label: t('common.view'),
                type: 'view',
                href: route('admin.accounting.posting-rules.edit', ruleSet.id),
                permission: 'accounting.view',
            },
        ];

        if (can('accounting.manage-posting-rules')) {
            actions.push(
                {
                    label: t('common.edit'),
                    type: 'edit',
                    href: route('admin.accounting.posting-rules.edit', ruleSet.id),
                    permission: 'accounting.manage-posting-rules',
                },
                {
                    label: t('pages.accounting.postingRules.duplicate'),
                    type: 'duplicate',
                    icon: Copy,
                    href: route('admin.accounting.posting-rules.create', ruleSet.id),
                    permission: 'accounting.manage-posting-rules',
                },
            );
        }

        return actions;
    };

    return (
        <>
            <Head title={t('pages.accounting.postingRules.title')} />
            <PageHeader
                title={t('pages.accounting.postingRules.title')}
                description={t('pages.accounting.postingRules.description')}
            />

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.accounting.postingRules.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="w-auto min-w-[10rem]"
                    options={[
                        { value: '', label: t('common.allStatuses') },
                        { value: 'active', label: t('common.active') },
                        { value: 'inactive', label: t('common.inactive') },
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={ruleSets?.data ?? []}
                pagination={ruleSets}
                filters={filters}
                indexRoute="admin.accounting.posting-rules.index"
                rowActions={rowActions}
                emptyMessage={t('pages.accounting.postingRules.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
