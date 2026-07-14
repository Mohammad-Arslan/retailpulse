import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ assets = [], categories = [], branches = [] }) {
    const { t } = useTranslation();
    const can = useCan();
    const [disposeAsset, setDisposeAsset] = useState(null);

    const form = useForm({
        asset_code: '',
        name: '',
        category_id: categories[0]?.id ? String(categories[0].id) : '',
        acquisition_cost: '',
        acquisition_date: new Date().toISOString().slice(0, 10),
        useful_life_months: categories[0]?.default_useful_life_months
            ? String(categories[0].default_useful_life_months)
            : '60',
        salvage_value: '0',
        branch_id: branches[0]?.id ? String(branches[0].id) : '',
        location: '',
    });

    const disposeForm = useForm({
        disposal_date: new Date().toISOString().slice(0, 10),
        proceeds_amount: '0',
    });

    const columns = useMemo(
        () => [
            {
                id: 'code',
                header: t('pages.accounting.fixedAssets.columns.code'),
                cell: ({ row }) => row.original.asset_code,
            },
            { id: 'name', header: t('common.name'), cell: ({ row }) => row.original.name },
            {
                id: 'category',
                header: t('common.category'),
                cell: ({ row }) => row.original.category_name ?? '—',
            },
            {
                id: 'cost',
                header: t('pages.accounting.fixedAssets.columns.acquisitionCost'),
                cell: ({ row }) => row.original.acquisition_cost,
            },
            {
                id: 'nbv',
                header: t('pages.accounting.fixedAssets.columns.netBookValue'),
                cell: ({ row }) => row.original.net_book_value,
            },
            { id: 'status', header: t('common.status'), cell: ({ row }) => row.original.status },
        ],
        [t],
    );

    const rowActions = (asset) => {
        if (!can('accounting.manage-assets') || asset.status !== 'active') {
            return [];
        }

        return [
            {
                label: t('pages.accounting.fixedAssets.dispose'),
                type: 'edit',
                permission: 'accounting.manage-assets',
                onClick: () => {
                    setDisposeAsset(asset);
                    disposeForm.clearErrors();
                    disposeForm.setData({
                        disposal_date: new Date().toISOString().slice(0, 10),
                        proceeds_amount: '0',
                    });
                },
            },
        ];
    };

    const categoryOptions = useMemo(
        () => categories.map((c) => ({ value: String(c.id), label: c.name })),
        [categories],
    );

    return (
        <>
            <Head title={t('pages.accounting.fixedAssets.title')} />
            <PageHeader
                title={t('pages.accounting.fixedAssets.title')}
                description={t('pages.accounting.fixedAssets.description')}
            >
                {can('accounting.manage-assets') && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            router.post(
                                route('admin.accounting.fixed-assets.run-depreciation'),
                                { date: new Date().toISOString().slice(0, 10) },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {t('pages.accounting.fixedAssets.runDepreciation')}
                    </Button>
                )}
            </PageHeader>
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <DataTable
                        columns={columns}
                        data={assets}
                        emptyMessage={t('pages.accounting.fixedAssets.empty')}
                        rowActions={rowActions}
                    />
                </div>
                <FormCard title={t('pages.accounting.fixedAssets.createTitle')}>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('admin.accounting.fixed-assets.store'), {
                                onSuccess: () => form.reset(),
                            });
                        }}
                        className="space-y-4"
                    >
                        <AdminFormField
                            label={t('pages.accounting.fixedAssets.fields.assetCode')}
                            error={form.errors.asset_code}
                            required
                        >
                            <input
                                id="asset_code"
                                className="rp-form-input"
                                value={form.data.asset_code}
                                onChange={(e) => form.setData('asset_code', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('common.name')} error={form.errors.name} required>
                            <input
                                id="asset_name"
                                className="rp-form-input"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('common.category')} error={form.errors.category_id} required>
                            <Select
                                options={categoryOptions}
                                value={form.data.category_id}
                                onChange={(value) => form.setData('category_id', value ?? '')}
                                placeholder={t('pages.accounting.fixedAssets.selectCategory')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.fixedAssets.fields.acquisitionCost')}
                            error={form.errors.acquisition_cost}
                            required
                        >
                            <input
                                id="acquisition_cost"
                                type="number"
                                min="0.01"
                                step="0.01"
                                className="rp-form-input"
                                value={form.data.acquisition_cost}
                                onChange={(e) => form.setData('acquisition_cost', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.fixedAssets.fields.acquisitionDate')}
                            error={form.errors.acquisition_date}
                            required
                        >
                            <input
                                id="acquisition_date"
                                type="date"
                                className="rp-form-input"
                                value={form.data.acquisition_date}
                                onChange={(e) => form.setData('acquisition_date', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.fixedAssets.fields.usefulLifeMonths')}
                            error={form.errors.useful_life_months}
                            required
                        >
                            <input
                                id="useful_life_months"
                                type="number"
                                min="1"
                                className="rp-form-input"
                                value={form.data.useful_life_months}
                                onChange={(e) => form.setData('useful_life_months', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('common.branch')} error={form.errors.branch_id}>
                            <Select
                                options={[
                                    { value: '', label: t('common.allBranches') },
                                    ...branches.map((b) => ({ value: String(b.id), label: b.name })),
                                ]}
                                value={form.data.branch_id}
                                onChange={(value) => form.setData('branch_id', value ?? '')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.fixedAssets.fields.location')}
                            error={form.errors.location}
                        >
                            <input
                                id="location"
                                className="rp-form-input"
                                value={form.data.location}
                                onChange={(e) => form.setData('location', e.target.value)}
                            />
                        </AdminFormField>
                        <Button type="submit" disabled={form.processing}>
                            {t('pages.accounting.fixedAssets.createSubmit')}
                        </Button>
                    </form>
                </FormCard>
            </div>

            <Modal show={Boolean(disposeAsset)} onClose={() => setDisposeAsset(null)} maxWidth="md">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (!disposeAsset) {
                            return;
                        }
                        disposeForm.post(route('admin.accounting.fixed-assets.dispose', disposeAsset.id), {
                            preserveScroll: true,
                            onSuccess: () => setDisposeAsset(null),
                        });
                    }}
                    className="p-6"
                >
                    <h2 className="text-lg font-semibold">
                        {t('pages.accounting.fixedAssets.disposeTitle', {
                            code: disposeAsset?.asset_code ?? '',
                        })}
                    </h2>
                    <div className="mt-5 space-y-4">
                        <AdminFormField
                            label={t('pages.accounting.fixedAssets.fields.disposalDate')}
                            error={disposeForm.errors.disposal_date}
                            required
                        >
                            <input
                                type="date"
                                className="rp-form-input"
                                value={disposeForm.data.disposal_date}
                                onChange={(e) => disposeForm.setData('disposal_date', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.fixedAssets.fields.proceedsAmount')}
                            error={disposeForm.errors.proceeds_amount}
                            required
                        >
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className="rp-form-input"
                                value={disposeForm.data.proceeds_amount}
                                onChange={(e) => disposeForm.setData('proceeds_amount', e.target.value)}
                            />
                        </AdminFormField>
                    </div>
                    <div className="mt-6 flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setDisposeAsset(null)}>
                            {t('common.back')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={disposeForm.processing}>
                            {t('pages.accounting.fixedAssets.disposeSubmit')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
