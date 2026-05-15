import ProductFormFields from '@/Components/admin/ProductFormFields';
import PageHeader from '@/Components/common/PageHeader';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Edit({
    product,
    productTypes,
    categories,
    brands,
    units,
    branches,
    canShowCost,
}) {
    const can = useCan();
    const confirm = useConfirm();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, delete: destroy } = useForm({
        name: product.name,
        description: product.description ?? '',
        category_id: product.category_id ?? '',
        brand_id: product.brand_id ?? '',
        unit_id: product.unit_id ?? '',
        track_batches: product.track_batches,
        is_active: product.is_active,
        type: product.type,
        default_cost_price: product.variants[0]?.cost_price ?? '0',
        default_sell_price: product.variants[0]?.sell_price ?? '0',
        variant_attributes: product.variant_attributes?.length
            ? product.variant_attributes
            : [{ name: '', options: [''] }],
        variants: product.variants ?? [],
        bundle_items: product.bundle_items ?? [],
        branch_prices: product.branch_prices ?? [],
        regenerate_variants: false,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.products.update', product.id));
    };

    const remove = async () => {
        const confirmed = await confirm({
            title: t('confirm.deleteTitle'),
            description: t('confirm.deleteProduct', { name: product.name }),
            confirmLabel: t('common.delete'),
            cancelLabel: t('confirm.cancel'),
            variant: 'destructive',
        });

        if (confirmed) {
            destroy(route('admin.products.destroy', product.id));
        }
    };

    return (
        <AdminLayout>
            <Head title={t('pages.products.editTitle', { name: product.name })} />
            <PageHeader title={t('pages.products.editTitle', { name: product.name })}>
                <Link href={route('admin.products.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="max-w-3xl space-y-5">
                <ProductFormFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    productTypes={productTypes}
                    categories={categories}
                    brands={brands}
                    units={units}
                    branches={branches}
                    canShowCost={canShowCost}
                    isEdit
                    productId={product.id}
                />
                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('pages.products.saveChanges')}
                    </button>
                    {can('products.delete') && (
                        <button type="button" onClick={remove} className="rp-btn-outline text-red-600">
                            {t('common.delete')}
                        </button>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
