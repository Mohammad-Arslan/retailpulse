import PageHeader from '@/Components/common/PageHeader';
import ImportExportToolbar from '@/Components/import-export/ImportExportToolbar';
import { useImportJobsTray } from '@/Components/import-export/ImportJobsTray';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
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

            <div className="overflow-hidden rounded-lg border bg-card">
                <table className="w-full text-left text-sm">
                    <thead className="border-b bg-muted/40 text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3">{t('pages.supplierPriceLists.columns.name')}</th>
                            <th className="px-4 py-3">{t('pages.supplierPriceLists.columns.supplier')}</th>
                            <th className="px-4 py-3">{t('pages.supplierPriceLists.columns.validFrom')}</th>
                            <th className="px-4 py-3">{t('pages.supplierPriceLists.columns.validTo')}</th>
                            <th className="px-4 py-3">{t('pages.supplierPriceLists.columns.items')}</th>
                            <th className="px-4 py-3">{t('pages.supplierPriceLists.columns.status')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {priceLists.data?.length ? (
                            priceLists.data.map((list) => (
                                <tr key={list.id} className="border-b">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={route('admin.supplier-price-lists.edit', list.id)}
                                            className="font-medium text-teal-600 hover:underline"
                                        >
                                            {list.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">{list.supplier}</td>
                                    <td className="px-4 py-3">{list.valid_from ?? '—'}</td>
                                    <td className="px-4 py-3">{list.valid_to ?? '—'}</td>
                                    <td className="px-4 py-3">{list.items_count}</td>
                                    <td className="px-4 py-3">
                                        {list.is_active ? t('common.active') : t('common.inactive')}
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                                    {t('pages.supplierPriceLists.empty')}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}

export default withAdminLayout(Index);
