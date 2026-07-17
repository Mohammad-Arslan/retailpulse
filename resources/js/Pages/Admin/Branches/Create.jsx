import OperatingHoursFields from '@/Components/admin/OperatingHoursFields';
import BranchWarehouseSection from '@/Components/admin/BranchWarehouseSection';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { useBranchCodeSuggestion } from '@/Hooks/useBranchCodeSuggestion';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

const WEEKDAYS = [0, 1, 2, 3, 4, 5, 6];

export default function Create({
    defaultOperatingHours,
    operationalOptions,
    warehousePicker = [],
}) {
    const { t } = useTranslation();
    const { currencies, timezones, defaults } = operationalOptions;
    const defaultInitialWarehouseId =
        warehousePicker.length === 1 ? String(warehousePicker[0].id) : '';
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        address: '',
        currency: defaults.currency,
        timezone: defaults.timezone,
        operating_hours: defaultOperatingHours,
        weekend_days: null,
        receipt_footer: '',
        is_active: true,
        initial_warehouse_id: defaultInitialWarehouseId,
    });

    const toggleWeekendDay = (day) => {
        const current = data.weekend_days ?? [];
        const next = current.includes(day) ? current.filter((d) => d !== day) : [...current, day];
        setData('weekend_days', next);
    };

    const { suggestedCode, loading } = useBranchCodeSuggestion({
        name: data.name,
        enabled: Boolean(data.name?.trim()),
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.branches.store'));
    };

    return (
        <AdminLayout>
            <Head title={t('pages.branches.createTitle')} />

            <PageHeader
                title={t('pages.branches.createTitle')}
                description={t('pages.branches.createDescription')}
            >
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
                        <div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
                            <div className="xl:col-span-6">
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
                            </div>
                            <div className="xl:col-span-6">
                                {suggestedCode && (
                                    <div className="rounded-xl border border-rp-border bg-rp-surface-inset/50 px-4 py-3 dark:bg-white/4">
                                        <p className="text-xs font-semibold tracking-widest text-rp-text-muted uppercase">
                                            {t('pages.branches.fields.code')}
                                        </p>
                                        <p className="mt-1 font-mono text-sm font-semibold text-rp-text">
                                            {suggestedCode}
                                        </p>
                                        <p className="mt-1 text-xs text-rp-text-muted">
                                            {loading
                                                ? t('pages.branches.codeGenerating')
                                                : t('pages.branches.codeUniqueHint')}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                        <AdminFormField
                            label={t('pages.branches.fields.address')}
                            id="address"
                            error={errors.address}
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
                                    onChange={(value) => setData('currency', value ?? defaults.currency)}
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
                                    onChange={(value) => setData('timezone', value ?? defaults.timezone)}
                                    isSearchable
                                />
                            </AdminFormField>
                        </div>
                        <p className="text-xs text-rp-text-muted">
                            {t('pages.branches.operationalDefaultsHint')}
                        </p>
                        <AdminFormField
                            label={t('pages.branches.fields.receiptFooter')}
                            id="receipt_footer"
                            error={errors.receipt_footer}
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
                        <AdminFormField
                            label={t('pages.branches.fields.weekendDays')}
                            error={errors.weekend_days}
                            hint={t('pages.branches.hints.weekendDays')}
                        >
                            <label className="rp-checkbox-label mb-2">
                                <input
                                    type="checkbox"
                                    checked={data.weekend_days !== null}
                                    onChange={(e) => setData('weekend_days', e.target.checked ? [] : null)}
                                    className="accent-teal-600"
                                />
                                {t('pages.branches.overrideWeekendDays')}
                            </label>
                            {data.weekend_days !== null && (
                                <div className="flex flex-wrap gap-3">
                                    {WEEKDAYS.map((day) => (
                                        <label key={day} className="rp-checkbox-label">
                                            <input
                                                type="checkbox"
                                                checked={(data.weekend_days ?? []).includes(day)}
                                                onChange={() => toggleWeekendDay(day)}
                                                className="accent-teal-600"
                                            />
                                            {t(`pages.hrSettings.weekdays.${day}`)}
                                        </label>
                                    ))}
                                </div>
                            )}
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
                    mode="create"
                    warehousePicker={warehousePicker}
                    initialWarehouseId={data.initial_warehouse_id}
                    errors={errors}
                    onInitialWarehouseChange={(value) => setData('initial_warehouse_id', value ?? '')}
                />

                <div className="flex flex-wrap gap-2">
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('pages.branches.createSubmit')}
                    </button>
                </div>
            </form>
        </AdminLayout>
    );
}
