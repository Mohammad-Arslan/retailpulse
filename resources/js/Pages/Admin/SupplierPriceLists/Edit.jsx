import VariantSearchPicker from '@/Components/admin/VariantSearchPicker';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

function Edit({ priceList, currencies = [] }) {
    const { t } = useTranslation();
    const [items, setItems] = useState(
        () =>
            priceList.items?.map((i) => ({
                variant: i.product_variant_id
                    ? { id: i.product_variant_id, sku: i.sku, name: i.product_name }
                    : null,
                unit_price: String(i.unit_price),
                min_qty: String(i.min_qty),
                lead_time_days: i.lead_time_days != null ? String(i.lead_time_days) : '',
            })) ?? [],
    );
    const [form, setForm] = useState({
        name: priceList.name ?? '',
        valid_from: priceList.valid_from ?? '',
        valid_to: priceList.valid_to ?? '',
        currency_code: priceList.currency_code ?? 'USD',
        is_active: Boolean(priceList.is_active),
    });

    const submit = (e) => {
        e.preventDefault();
        router.put(route('admin.supplier-price-lists.update', priceList.id), {
            ...form,
            items: items
                .filter((i) => i.variant)
                .map((i) => ({
                    product_variant_id: i.variant.id,
                    unit_price: Number(i.unit_price),
                    min_qty: Number(i.min_qty || 1),
                    lead_time_days: i.lead_time_days ? Number(i.lead_time_days) : null,
                })),
        });
    };

    return (
        <>
            <Head title={t('pages.supplierPriceLists.editTitle', { name: priceList.name })} />
            <PageHeader
                title={t('pages.supplierPriceLists.editTitle', { name: priceList.name })}
                description={priceList.supplier?.name}
            >
                <Link href={route('admin.supplier-price-lists.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="max-w-4xl space-y-5">
                <FormCard>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField label={t('pages.supplierPriceLists.fields.name')} id="name">
                            <input
                                id="name"
                                className="rp-form-input"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.supplierPriceLists.fields.currency')} id="currency_code">
                            <Select
                                options={currencies}
                                value={form.currency_code}
                                onChange={(v) => setForm({ ...form, currency_code: v ?? 'USD' })}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.supplierPriceLists.fields.validFrom')} id="valid_from">
                            <input
                                type="date"
                                className="rp-form-input"
                                value={form.valid_from}
                                onChange={(e) => setForm({ ...form, valid_from: e.target.value })}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.supplierPriceLists.fields.validTo')} id="valid_to">
                            <input
                                type="date"
                                className="rp-form-input"
                                value={form.valid_to}
                                onChange={(e) => setForm({ ...form, valid_to: e.target.value })}
                            />
                        </AdminFormField>
                        <label className="rp-checkbox-label">
                            <input
                                type="checkbox"
                                checked={form.is_active}
                                onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                            />
                            <span>{t('common.active')}</span>
                        </label>
                    </div>
                </FormCard>

                <FormCard title={t('pages.supplierPriceLists.lineItems')}>
                    <div className="mb-3 hidden gap-3 text-xs font-semibold tracking-wide text-rp-text-secondary uppercase sm:grid sm:grid-cols-12">
                        <span className="sm:col-span-5">{t('pages.supplierPriceLists.fields.variant')}</span>
                        <span className="sm:col-span-2">{t('pages.supplierPriceLists.fields.unitPrice')}</span>
                        <span className="sm:col-span-2">{t('pages.supplierPriceLists.fields.minQty')}</span>
                        <span className="sm:col-span-2">{t('pages.supplierPriceLists.fields.leadTime')}</span>
                        <span className="sm:col-span-1" />
                    </div>
                    {items.map((item, index) => (
                        <div key={index} className="mb-4 grid gap-3 border-b pb-4 sm:grid-cols-12">
                            <div className="sm:col-span-5">
                                <VariantSearchPicker
                                    value={item.variant}
                                    onChange={(variant) =>
                                        setItems((prev) =>
                                            prev.map((row, i) => (i === index ? { ...row, variant } : row)),
                                        )
                                    }
                                />
                            </div>
                            <input
                                className="rp-form-input sm:col-span-2"
                                placeholder={t('pages.supplierPriceLists.fields.unitPrice')}
                                value={item.unit_price}
                                onChange={(e) =>
                                    setItems((prev) =>
                                        prev.map((row, i) => (i === index ? { ...row, unit_price: e.target.value } : row)),
                                    )
                                }
                            />
                            <input
                                className="rp-form-input sm:col-span-2"
                                placeholder={t('pages.supplierPriceLists.fields.minQty')}
                                value={item.min_qty}
                                onChange={(e) =>
                                    setItems((prev) =>
                                        prev.map((row, i) => (i === index ? { ...row, min_qty: e.target.value } : row)),
                                    )
                                }
                            />
                            <input
                                className="rp-form-input sm:col-span-2"
                                placeholder={t('pages.supplierPriceLists.fields.leadTime')}
                                value={item.lead_time_days}
                                onChange={(e) =>
                                    setItems((prev) =>
                                        prev.map((row, i) =>
                                            i === index ? { ...row, lead_time_days: e.target.value } : row,
                                        ),
                                    )
                                }
                            />
                            <button
                                type="button"
                                className="rp-btn-outline sm:col-span-1"
                                onClick={() => setItems((prev) => prev.filter((_, i) => i !== index))}
                            >
                                <Trash2 className="h-4 w-4" />
                            </button>
                        </div>
                    ))}
                    <button
                        type="button"
                        className="rp-btn-outline"
                        onClick={() => setItems((prev) => [...prev, { variant: null, unit_price: '', min_qty: '1', lead_time_days: '' }])}
                    >
                        <Plus className="h-4 w-4" />
                        {t('pages.supplierPriceLists.addLine')}
                    </button>
                </FormCard>

                <div className="flex justify-end">
                    <button type="submit" className="rp-btn-primary">
                        {t('pages.supplierPriceLists.updateSubmit')}
                    </button>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Edit);
