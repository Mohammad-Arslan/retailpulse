import ProductFormFields from '@/Components/admin/ProductFormFields';
import PageHeader from '@/Components/common/PageHeader';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    productImageSyncErrorMessage,
    syncProductImages,
} from '@/lib/productImagesApi';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

function clearPendingImages(pendingImages, setPendingImages) {
    pendingImages.forEach((item) => {
        if (item.previewUrl) {
            URL.revokeObjectURL(item.previewUrl);
        }
    });
    setPendingImages([]);
}

export default function Create({
    productTypes,
    categories,
    brands,
    units,
    branches,
    canShowCost,
}) {
    const { t } = useTranslation();
    const [pendingImages, setPendingImages] = useState([]);
    const [isSyncingImages, setIsSyncingImages] = useState(false);
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
        default_reorder_point: '',
        variant_attributes: [{ name: 'Size', options: ['S', 'M', 'L'] }],
        variants: [],
        bundle_items: [],
        branch_prices: [],
        regenerate_variants: false,
    });

    const submit = (e) => {
        e.preventDefault();

        const pendingFiles = pendingImages.map((item) => item.file);
        const hasPendingImages = pendingFiles.length > 0;

        post(route('admin.products.store'), {
            onSuccess: async (page) => {
                if (!hasPendingImages) {
                    return;
                }

                const productId = page.props.product?.id;

                if (!productId) {
                    toast.error(t('pages.products.imageSyncFailed'));
                    return;
                }

                setIsSyncingImages(true);

                try {
                    await syncProductImages(productId, { images: pendingFiles });
                    clearPendingImages(pendingImages, setPendingImages);
                    router.reload({ only: ['product'] });
                } catch (error) {
                    toast.error(
                        productImageSyncErrorMessage(error, t('pages.products.imageSyncFailed')),
                    );
                } finally {
                    setIsSyncingImages(false);
                }
            },
        });
    };

    const isSubmitting = processing || isSyncingImages;

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
            <form onSubmit={submit} className="space-y-5">
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
                    pendingImages={pendingImages}
                    onPendingImagesChange={setPendingImages}
                    removedImageIds={[]}
                    onRemovedImageIdsChange={() => {}}
                />
                <button type="submit" disabled={isSubmitting} className="rp-btn-primary">
                    {t('pages.products.createSubmit')}
                </button>
            </form>
        </AdminLayout>
    );
}
