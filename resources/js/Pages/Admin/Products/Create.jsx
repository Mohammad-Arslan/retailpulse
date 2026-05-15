import ProductFormFields from '@/Components/admin/ProductFormFields';
import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Create({
    productTypes,
    categories,
    brands,
    units,
    branches,
    canShowCost,
}) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        type: 'standard',
        name: '',
        description: '',
        category_id: '',
        brand_id: '',
        unit_id: '',
        track_batches: false,
        is_active: true,
        default_cost_price: '0',
        default_sell_price: '0',
        variant_attributes: [{ name: 'Size', options: ['S', 'M', 'L'] }],
        variants: [],
        bundle_items: [],
        branch_prices: [],
        regenerate_variants: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.products.store'));
    };

    return (
        <AdminLayout>
            <Head title={t('pages.products.createTitle')} />
            <PageHeader
                title={t('pages.products.createTitle')}
                description={t('pages.products.createDescription')}
            >
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
                />
                <button type="submit" disabled={processing} className="rp-btn-primary">
                    {t('pages.products.createSubmit')}
                </button>
            </form>
        </AdminLayout>
    );
}
