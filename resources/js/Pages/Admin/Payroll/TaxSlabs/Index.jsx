import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function formatAmount(value) {
    if (value == null) return '—';
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function Index({ slabs, entities, filters }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.payroll.tax-slabs.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'legalEntity',
                header: t('pages.taxSlabs.columns.legalEntity'),
                cell: ({ row }) => row.original.legal_entity ?? '—',
            },
            {
                id: 'effectiveDates',
                header: t('pages.taxSlabs.columns.effectiveDates'),
                cell: ({ row }) =>
                    `${row.original.effective_from ?? '—'} → ${row.original.effective_to ?? '∞'}`,
            },
            {
                id: 'lowerBound',
                header: t('pages.taxSlabs.columns.lowerBound'),
                cell: ({ row }) => formatAmount(row.original.lower_bound),
            },
            {
                id: 'upperBound',
                header: t('pages.taxSlabs.columns.upperBound'),
                cell: ({ row }) => (row.original.upper_bound != null ? formatAmount(row.original.upper_bound) : '∞'),
            },
            {
                id: 'fixedAmount',
                header: t('pages.taxSlabs.columns.fixedAmount'),
                cell: ({ row }) => formatAmount(row.original.fixed_amount),
            },
            {
                id: 'marginalRate',
                header: t('pages.taxSlabs.columns.marginalRate'),
                cell: ({ row }) => `${row.original.marginal_rate}%`,
            },
            {
                id: 'status',
                header: t('pages.taxSlabs.columns.status'),
                cell: ({ row }) =>
                    t(`pages.taxSlabs.statuses.${row.original.status}`, { defaultValue: row.original.status }),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.taxSlabs.indexTitle')} />
            <PageHeader
                title={t('pages.taxSlabs.indexTitle')}
                description={t('pages.taxSlabs.indexDescription')}
            />

            <form onSubmit={search} className="mb-4 flex flex-wrap items-end gap-3">
                <Select name="legal_entity_id" defaultValue={filters.legal_entity_id ?? ''} className="min-w-[200px]">
                    <option value="">{t('pages.taxSlabs.allEntities')}</option>
                    {(entities ?? []).map((entity) => (
                        <option key={entity.id} value={entity.id}>
                            {entity.name}
                        </option>
                    ))}
                </Select>
                <Select name="status" defaultValue={filters.status ?? ''} className="min-w-[140px]">
                    <option value="">{t('pages.taxSlabs.allStatuses')}</option>
                    {['active', 'inactive'].map((status) => (
                        <option key={status} value={status}>
                            {t(`pages.taxSlabs.statuses.${status}`)}
                        </option>
                    ))}
                </Select>
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable columns={columns} data={slabs.data ?? []} pagination={slabs} />
        </>
    );
}

export default withAdminLayout(Index);
