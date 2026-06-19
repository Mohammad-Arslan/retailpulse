import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

function Quarantine({ items }) {
    const can = useCan();
    const { t } = useTranslation();

    const release = (item) => {
        if (!confirm(t('pages.quarantine.confirmRelease'))) return;
        router.post(route('admin.inventory.quarantine.release'), {
            warehouse_id: item.warehouse.id,
            product_variant_id: item.variant.id,
            batch_id: item.batch?.id ?? null,
            bin_location_id: item.bin?.id ?? null,
            quantity: item.quantity_in_quarantine,
        });
    };

    const scrap = (item) => {
        if (!confirm(t('pages.quarantine.confirmScrap'))) return;
        router.post(route('admin.inventory.quarantine.scrap'), {
            warehouse_id: item.warehouse.id,
            product_variant_id: item.variant.id,
            batch_id: item.batch?.id ?? null,
            bin_location_id: item.bin?.id ?? null,
            quantity: item.quantity_in_quarantine,
        });
    };

    return (
        <>
            <Head title={t('pages.quarantine.title')} />
            <PageHeader title={t('pages.quarantine.title')} description={t('pages.quarantine.description')} />
            <div className="overflow-x-auto rounded-xl border border-rp-border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-rp-border bg-rp-surface-inset text-left text-rp-text-muted">
                            <th className="px-4 py-3">{t('pages.inventory.columns.product')}</th>
                            <th className="px-4 py-3">{t('pages.inventory.columns.warehouse')}</th>
                            <th className="px-4 py-3">{t('pages.quarantine.qty')}</th>
                            <th className="px-4 py-3">{t('common.actions')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr key={item.id} className="border-b border-rp-border/50">
                                <td className="px-4 py-3">
                                    <div className="font-medium">{item.variant.product_name}</div>
                                    <div className="text-xs text-rp-text-muted">{item.variant.sku}</div>
                                </td>
                                <td className="px-4 py-3">
                                    {item.warehouse.name}
                                    {item.bin_code && (
                                        <div className="text-xs font-mono text-rp-text-muted">{item.bin_code}</div>
                                    )}
                                </td>
                                <td className="px-4 py-3 font-semibold text-amber-600">
                                    {item.quantity_in_quarantine}
                                </td>
                                <td className="px-4 py-3">
                                    {can('inventory.release-quarantine') && (
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                onClick={() => release(item)}
                                                className="rp-btn-outline text-xs"
                                            >
                                                {t('pages.quarantine.release')}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => scrap(item)}
                                                className="rp-btn-outline text-xs text-red-600"
                                            >
                                                {t('pages.quarantine.scrap')}
                                            </button>
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {items.length === 0 && (
                    <p className="p-6 text-center text-sm text-rp-text-muted">{t('pages.quarantine.empty')}</p>
                )}
            </div>
        </>
    );
}

export default withAdminLayout(Quarantine);
