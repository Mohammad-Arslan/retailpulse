import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function BranchStockSettings({ variants, filters, branchId }) {
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.inventory.branch-stock-settings'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    return (
        <>
            <Head title={t('pages.branchStockSettings.title')} />
            <PageHeader
                title={t('pages.branchStockSettings.title')}
                description={t('pages.branchStockSettings.description')}
            />

            {branchId === null ? (
                <p className="text-sm text-amber-600">{t('pages.branchStockSettings.selectBranchHint')}</p>
            ) : (
                <>
                    <form onSubmit={search} className="rp-filter-bar mb-4">
                        <div className="rp-search-inset flex-1">
                            <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                            <input
                                name="search"
                                defaultValue={filters.search ?? ''}
                                placeholder={t('pages.branchStockSettings.searchPlaceholder')}
                                className="rp-search-input"
                            />
                        </div>
                        <button type="submit" className="rp-btn-outline">
                            {t('common.search')}
                        </button>
                    </form>

                    <div className="overflow-x-auto rounded-xl border border-rp-border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-rp-border bg-rp-surface-muted text-left text-rp-text-muted">
                                    <th className="px-4 py-3">{t('pages.inventory.columns.product')}</th>
                                    <th className="px-4 py-3">{t('pages.branchStockSettings.defaultReorder')}</th>
                                    <th className="px-4 py-3">{t('pages.branchStockSettings.branchReorder')}</th>
                                    <th className="px-4 py-3">{t('pages.branchStockSettings.safetyStock')}</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {variants.map((variant) => (
                                    <VariantRow
                                        key={variant.id}
                                        variant={variant}
                                        branchId={branchId}
                                    />
                                ))}
                            </tbody>
                        </table>
                        {variants.length === 0 && (
                            <p className="p-6 text-center text-sm text-rp-text-muted">
                                {t('pages.branchStockSettings.empty')}
                            </p>
                        )}
                    </div>
                </>
            )}
        </>
    );
}

function VariantRow({ variant, branchId }) {
    const { t } = useTranslation();
    const { data, setData, put, processing } = useForm({
        branch_id: branchId,
        product_variant_id: variant.id,
        reorder_point: variant.reorder_point ?? '',
        safety_stock_qty: variant.safety_stock_qty ?? '',
    });

    const save = (e) => {
        e.preventDefault();
        put(route('admin.inventory.branch-stock-settings.update'), { preserveScroll: true });
    };

    return (
        <tr className="border-b border-rp-border/50">
            <td className="px-4 py-3">
                <div className="font-medium">{variant.product_name}</div>
                <div className="text-xs text-rp-text-muted">
                    {variant.name} · {variant.sku}
                </div>
            </td>
            <td className="px-4 py-3 text-rp-text-muted">{variant.default_reorder_point ?? '—'}</td>
            <td className="px-4 py-3">
                <input
                    type="number"
                    min="0"
                    className="rp-form-input w-24"
                    value={data.reorder_point}
                    onChange={(e) => setData('reorder_point', e.target.value)}
                />
            </td>
            <td className="px-4 py-3">
                <input
                    type="number"
                    min="0"
                    className="rp-form-input w-24"
                    value={data.safety_stock_qty}
                    onChange={(e) => setData('safety_stock_qty', e.target.value)}
                />
            </td>
            <td className="px-4 py-3 text-right">
                <button type="button" onClick={save} disabled={processing} className="rp-btn-outline text-xs">
                    {t('common.save')}
                </button>
            </td>
        </tr>
    );
}

export default withAdminLayout(BranchStockSettings);
