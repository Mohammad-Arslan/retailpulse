import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ schemes, entities, filters }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.payroll.statutory-schemes.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const entityOptions = useMemo(
        () => [
            { value: '', label: t('pages.statutorySchemes.allEntities') },
            ...(entities ?? []).map((entity) => ({ value: String(entity.id), label: entity.name })),
        ],
        [entities, t],
    );

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.statutorySchemes.allStatuses') },
            ...['active', 'inactive'].map((status) => ({
                value: status,
                label: t(`pages.statutorySchemes.statuses.${status}`),
            })),
        ],
        [t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'code',
                header: t('pages.statutorySchemes.columns.code'),
                cell: ({ row }) => (
                    <div>
                        <div className="text-sm font-semibold text-rp-text-primary">{row.original.code}</div>
                        <div className="text-xs text-rp-text-muted">{row.original.name}</div>
                    </div>
                ),
            },
            {
                id: 'legalEntity',
                header: t('pages.statutorySchemes.columns.legalEntity'),
                cell: ({ row }) => row.original.legal_entity ?? '—',
            },
            {
                id: 'rates',
                header: t('pages.statutorySchemes.columns.rates'),
                cell: ({ row }) => (
                    <div className="text-sm">
                        <div>
                            {t('pages.statutorySchemes.employeeRate')}: {row.original.employee_rate}%
                        </div>
                        <div>
                            {t('pages.statutorySchemes.employerRate')}: {row.original.employer_rate}%
                        </div>
                    </div>
                ),
            },
            {
                id: 'wageCeiling',
                header: t('pages.statutorySchemes.columns.wageCeiling'),
                cell: ({ row }) => (row.original.wage_ceiling != null ? Number(row.original.wage_ceiling).toLocaleString() : '—'),
            },
            {
                id: 'mappingKeys',
                header: t('pages.statutorySchemes.columns.mappingKeys'),
                cell: ({ row }) => (
                    <div className="space-y-1 text-xs">
                        <div className="text-rp-text-muted">
                            {t('pages.statutorySchemes.employeeKey')}: {row.original.account_mapping_key_employee ?? '—'}
                        </div>
                        <div className="text-rp-text-muted">
                            {t('pages.statutorySchemes.employerKey')}: {row.original.account_mapping_key_employer ?? '—'}
                        </div>
                    </div>
                ),
            },
            {
                id: 'effectiveDates',
                header: t('pages.statutorySchemes.columns.effectiveDates'),
                cell: ({ row }) =>
                    `${row.original.effective_from ?? '—'} → ${row.original.effective_to ?? '∞'}`,
            },
            {
                id: 'status',
                header: t('pages.statutorySchemes.columns.status'),
                cell: ({ row }) =>
                    t(`pages.statutorySchemes.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.statutorySchemes.indexTitle')} />
            <PageHeader
                title={t('pages.statutorySchemes.indexTitle')}
                description={t('pages.statutorySchemes.indexDescription')}
            />

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <Select
                    name="legal_entity_id"
                    defaultValue={filters.legal_entity_id ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={entityOptions}
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

            <DataTable columns={columns} data={schemes.data ?? []} pagination={schemes} />
        </>
    );
}

export default withAdminLayout(Index);
