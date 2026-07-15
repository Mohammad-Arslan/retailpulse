import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router } from '@inertiajs/react';
import { CircleDollarSign, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ components, filters }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.payroll.pay-components.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const typeOptions = useMemo(
        () => [
            { value: '', label: t('pages.payComponents.allTypes') },
            ...['earning', 'deduction', 'employer_contribution', 'statutory', 'reimbursement'].map((type) => ({
                value: type,
                label: t(`pages.payComponents.types.${type}`),
            })),
        ],
        [t],
    );

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.payComponents.allStatuses') },
            ...['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.payComponents.statuses.${status}`),
            })),
        ],
        [t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'code',
                header: t('pages.payComponents.columns.code'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-300">
                            <CircleDollarSign className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text-primary">{row.original.code}</div>
                            <div className="text-xs text-rp-text-muted">{row.original.name}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'type',
                header: t('pages.payComponents.columns.type'),
                cell: ({ row }) =>
                    t(`pages.payComponents.types.${row.original.type}`, { defaultValue: row.original.type }),
            },
            {
                id: 'calculationType',
                header: t('pages.payComponents.columns.calculationType'),
                cell: ({ row }) =>
                    t(`pages.payComponents.calculationTypes.${row.original.calculation_type}`, {
                        defaultValue: row.original.calculation_type,
                    }),
            },
            {
                id: 'basis',
                header: t('pages.payComponents.columns.basis'),
                cell: ({ row }) => row.original.basis_component ?? '—',
            },
            {
                id: 'rate',
                header: t('pages.payComponents.columns.rate'),
                cell: ({ row }) => (row.original.rate != null ? `${row.original.rate}%` : '—'),
            },
            {
                id: 'taxable',
                header: t('pages.payComponents.columns.taxable'),
                cell: ({ row }) => (row.original.taxable ? t('common.yes') : t('common.no')),
            },
            {
                id: 'accountMappingKey',
                header: t('pages.payComponents.columns.accountMappingKey'),
                cell: ({ row }) => row.original.account_mapping_key ?? '—',
            },
            {
                id: 'status',
                header: t('pages.payComponents.columns.status'),
                cell: ({ row }) =>
                    t(`pages.payComponents.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.payComponents.indexTitle')} />
            <PageHeader
                title={t('pages.payComponents.indexTitle')}
                description={t('pages.payComponents.indexDescription')}
            />

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.payComponents.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="type"
                    defaultValue={filters.type ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={typeOptions}
                />
                <Select
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={statusOptions}
                />
                <Button type="submit" variant="outline">
                    {t('common.search')}
                </Button>
            </form>

            <DataTable columns={columns} data={components.data ?? []} pagination={components} />
        </>
    );
}

export default withAdminLayout(Index);
