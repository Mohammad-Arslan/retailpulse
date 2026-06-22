import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import ImportExportToolbar from '@/Components/import-export/ImportExportToolbar';
import { useImportJobsTray } from '@/Components/import-export/ImportJobsTray';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ priceLists, filters, suppliers = [] }) {
    const can = useCan();
    const { t } = useTranslation();
    const { trackJob } = useImportJobsTray();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.supplier-price-lists.index'), Object.fromEntries(form), { preserveState: true });
    };

    const exportFilters = {
        search: filters.search ?? undefined,
        supplier_id: filters.supplier_id ?? undefined,
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                header: t('pages.supplierPriceLists.columns.name'),
                cell: ({ row }) => (
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={route('admin.supplier-price-lists.edit', row.original.id)}
                            className="font-medium text-teal-600 hover:underline"
                        >
                            {row.original.name}
                        </Link>
                        {row.original.expiring_soon && (
                            <span className="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
                                {t('pages.supplierPriceLists.badges.expiringSoon')}
                            </span>
                        )}
                    </div>
                ),
            },
            {
                id: 'supplier',
                accessorKey: 'supplier',
                header: t('pages.supplierPriceLists.columns.supplier'),
            },
            {
                id: 'valid_from',
                header: t('pages.supplierPriceLists.columns.validFrom'),
                cell: ({ row }) => row.original.valid_from ?? '—',
            },
            {
                id: 'valid_to',
                header: t('pages.supplierPriceLists.columns.validTo'),
                cell: ({ row }) => row.original.valid_to ?? '—',
            },
            {
                id: 'items_count',
                accessorKey: 'items_count',
                header: t('pages.supplierPriceLists.columns.items'),
            },
            {
                id: 'status',
                header: t('pages.supplierPriceLists.columns.status'),
                cell: ({ row }) =>
                    row.original.is_active ? t('common.active') : t('common.inactive'),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.supplierPriceLists.title')} />
            <PageHeader title={t('pages.supplierPriceLists.title')} description={t('pages.supplierPriceLists.description')}>
                <div className="flex flex-wrap items-center gap-2">
                    <ImportExportToolbar
                        entityType="supplier-price-lists"
                        entityLabel={t('pages.supplierPriceLists.title')}
                        exportOptions={{ filters: exportFilters }}
                        onJobStarted={trackJob}
                    />
                    {can('procurement.manage-suppliers') && (
                        <Link href={route('admin.supplier-price-lists.create')} className="rp-btn-primary">
                            <Plus className="h-4 w-4" />
                            {t('pages.supplierPriceLists.createTitle')}
                        </Link>
                    )}
                </div>
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.supplierPriceLists.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="supplier_id"
                    defaultValue={filters.supplier_id ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={[
                        { value: '', label: t('common.allSuppliers') },
                        ...suppliers.map((s) => ({ value: String(s.id), label: s.name })),
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={priceLists.data ?? []}
                pagination={priceLists}
                filters={filters}
                indexRoute="admin.supplier-price-lists.index"
                emptyMessage={t('pages.supplierPriceLists.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
