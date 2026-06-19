import OperatingHoursFields from '@/Components/admin/OperatingHoursFields';
import BranchWarehouseSection from '@/Components/admin/BranchWarehouseSection';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Edit({ branch, operationalOptions, warehouseOptions = [] }) {
    const can = useCan();
    const confirm = useConfirm();
    const { t } = useTranslation();
    const { currencies, timezones } = operationalOptions;
    const { data, setData, put, processing, errors, delete: destroy } = useForm({
        name: branch.name,
        address: branch.address ?? '',
        currency: branch.currency,
        timezone: branch.timezone,
        picking_strategy: branch.picking_strategy ?? 'fifo',
        operating_hours: branch.operating_hours,
        receipt_footer: branch.receipt_footer ?? '',
        is_active: branch.is_active,
        default_warehouse_id: branch.default_warehouse_id ?? '',
        cutover_date: branch.cutover_date ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.branches.update', branch.id));
    };

    const remove = async () => {
        const confirmed = await confirm({
            title: t('confirm.deleteTitle'),
            description: t('confirm.deleteBranch', { name: branch.name }),
            confirmLabel: t('common.delete'),
            cancelLabel: t('confirm.cancel'),
            variant: 'destructive',
        });

        if (confirmed) {
            destroy(route('admin.branches.destroy', branch.id));
        }
    };

    return (
        <AdminLayout>
            <Head title={t('pages.branches.editTitle', { name: branch.name })} />

            <PageHeader title={branch.name} description={branch.code}>
                <Link href={route('admin.branches.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="w-full space-y-5">
                <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
                    <FormCard className="max-w-none w-full">
                        <h3 className="rp-form-label mb-4">
                            {t('pages.branches.sections.general')}
                        </h3>
                        <AdminFormField
                            label={t('pages.branches.fields.name')}
                            id="name"
                            error={errors.name}
                        >
                            <input
                                id="name"
                                value={data.name}
                                className="rp-form-input w-full"
                                onChange={(e) => setData('name', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.branches.fields.code')}
                            id="code"
                        >
                            <input
                                id="code"
                                value={branch.code}
                                className="rp-form-input w-full bg-rp-surface-inset font-mono uppercase"
                                disabled
                                readOnly
                            />
                            <p className="mt-1 text-xs text-rp-text-muted">
                                {t('pages.branches.codeImmutableHint')}
                            </p>
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.branches.fields.address')}
                            id="address"
                        >
                            <textarea
                                id="address"
                                value={data.address}
                                rows={2}
                                className="rp-form-input w-full"
                                onChange={(e) => setData('address', e.target.value)}
                            />
                        </AdminFormField>
                        <label className="rp-checkbox-label">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="accent-teal-500"
                            />
                            {t('pages.branches.fields.active')}
                        </label>
                    </FormCard>

                    <FormCard className="max-w-none w-full">
                        <h3 className="rp-form-label mb-4">
                            {t('pages.branches.sections.settings')}
                        </h3>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <AdminFormField
                                label={t('pages.branches.fields.currency')}
                                id="currency"
                                error={errors.currency}
                            >
                                <Select
                                    id="currency"
                                    options={currencies}
                                    value={data.currency}
                                    onChange={(value) => setData('currency', value ?? branch.currency)}
                                    isSearchable
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.branches.fields.timezone')}
                                id="timezone"
                                error={errors.timezone}
                            >
                                <Select
                                    id="timezone"
                                    options={timezones}
                                    value={data.timezone}
                                    onChange={(value) => setData('timezone', value ?? branch.timezone)}
                                    isSearchable
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.branches.fields.pickingStrategy')}
                                id="picking_strategy"
                                error={errors.picking_strategy}
                            >
                                <Select
                                    id="picking_strategy"
                                    value={data.picking_strategy}
                                    onChange={(value) => setData('picking_strategy', value)}
                                    options={[
                                        {
                                            value: 'fifo',
                                            label: t('pages.branches.pickingStrategies.fifo'),
                                        },
                                        {
                                            value: 'fefo',
                                            label: t('pages.branches.pickingStrategies.fefo'),
                                        },
                                    ]}
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.branches.fields.cutoverDate')}
                                id="cutover_date"
                                error={errors.cutover_date}
                            >
                                <input
                                    id="cutover_date"
                                    type="datetime-local"
                                    value={data.cutover_date}
                                    className="rp-form-input w-full"
                                    onChange={(e) => setData('cutover_date', e.target.value)}
                                />
                                <p className="mt-1 text-xs text-rp-text-muted">
                                    {t('pages.branches.cutoverHint')}
                                </p>
                            </AdminFormField>
                        </div>
                        <AdminFormField
                            label={t('pages.branches.fields.receiptFooter')}
                            id="receipt_footer"
                        >
                            <textarea
                                id="receipt_footer"
                                value={data.receipt_footer}
                                rows={3}
                                className="rp-form-input w-full"
                                onChange={(e) =>
                                    setData('receipt_footer', e.target.value)
                                }
                            />
                        </AdminFormField>
                    </FormCard>
                </div>

                <div className="rp-card w-full px-5 py-5 sm:px-6 lg:px-8">
                    <h3 className="rp-form-label mb-4">
                        {t('pages.branches.sections.hours')}
                    </h3>
                    <OperatingHoursFields
                        hours={data.operating_hours}
                        onChange={(hours) => setData('operating_hours', hours)}
                        errors={errors}
                    />
                </div>

                <BranchWarehouseSection
                    mode="edit"
                    branchId={branch.id}
                    warehouses={branch.warehouses}
                    defaultWarehouseId={data.default_warehouse_id}
                    warehouseOptions={warehouseOptions}
                    errors={errors}
                    onDefaultWarehouseChange={(value) => setData('default_warehouse_id', value)}
                />

                <div className="flex flex-wrap gap-2">
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('pages.branches.saveChanges')}
                    </button>
                    {can('branches.delete') && (
                        <button
                            type="button"
                            onClick={remove}
                            className="rp-btn-outline border-rose-200 text-rose-500 hover:border-rose-500 hover:bg-rose-100 hover:text-rose-500"
                        >
                            {t('common.delete')}
                        </button>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
