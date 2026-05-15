import OperatingHoursFields from '@/Components/admin/OperatingHoursFields';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Edit({ branch, timezones }) {
    const can = useCan();
    const confirm = useConfirm();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, delete: destroy } = useForm({
        name: branch.name,
        code: branch.code,
        address: branch.address ?? '',
        currency: branch.currency,
        timezone: branch.timezone,
        operating_hours: branch.operating_hours,
        receipt_footer: branch.receipt_footer ?? '',
        is_active: branch.is_active,
        default_warehouse_id: branch.default_warehouse_id ?? '',
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

            <PageHeader
                title={branch.name}
                description={branch.code}
            >
                <Link href={route('admin.branches.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="space-y-5">
                <FormCard>
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
                            className="rp-form-input"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.branches.fields.code')}
                        id="code"
                        error={errors.code}
                    >
                        <input
                            id="code"
                            value={data.code}
                            className="rp-form-input font-mono uppercase"
                            onChange={(e) =>
                                setData('code', e.target.value.toUpperCase())
                            }
                            required
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.branches.fields.address')}
                        id="address"
                    >
                        <textarea
                            id="address"
                            value={data.address}
                            rows={2}
                            className="rp-form-input"
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

                <FormCard>
                    <h3 className="rp-form-label mb-4">
                        {t('pages.branches.sections.settings')}
                    </h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.branches.fields.currency')}
                            id="currency"
                            error={errors.currency}
                        >
                            <input
                                id="currency"
                                value={data.currency}
                                maxLength={3}
                                className="rp-form-input font-mono uppercase"
                                onChange={(e) =>
                                    setData('currency', e.target.value.toUpperCase())
                                }
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.branches.fields.timezone')}
                            id="timezone"
                            error={errors.timezone}
                        >
                            <select
                                id="timezone"
                                value={data.timezone}
                                className="rp-form-input"
                                onChange={(e) => setData('timezone', e.target.value)}
                            >
                                {timezones.map((tz) => (
                                    <option key={tz} value={tz}>
                                        {tz}
                                    </option>
                                ))}
                            </select>
                        </AdminFormField>
                    </div>
                    <AdminFormField
                        label={t('pages.branches.fields.defaultWarehouse')}
                        id="default_warehouse_id"
                        error={errors.default_warehouse_id}
                    >
                        <select
                            id="default_warehouse_id"
                            value={data.default_warehouse_id}
                            className="rp-form-input"
                            onChange={(e) =>
                                setData('default_warehouse_id', e.target.value)
                            }
                        >
                            {branch.warehouses.map((w) => (
                                <option key={w.id} value={w.id}>
                                    {w.name} ({w.code})
                                </option>
                            ))}
                        </select>
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.branches.fields.receiptFooter')}
                        id="receipt_footer"
                    >
                        <textarea
                            id="receipt_footer"
                            value={data.receipt_footer}
                            rows={3}
                            className="rp-form-input"
                            onChange={(e) =>
                                setData('receipt_footer', e.target.value)
                            }
                        />
                    </AdminFormField>
                </FormCard>

                <div className="rp-card max-w-4xl">
                    <h3 className="rp-form-label mb-3">
                        {t('pages.branches.sections.hours')}
                    </h3>
                    <OperatingHoursFields
                        hours={data.operating_hours}
                        onChange={(hours) => setData('operating_hours', hours)}
                        errors={errors}
                    />
                </div>

                <div className="flex flex-wrap gap-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rp-btn-primary"
                    >
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
