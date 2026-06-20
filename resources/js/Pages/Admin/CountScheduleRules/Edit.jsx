import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import CountScopeFields from '@/Components/admin/CountScopeFields';
import Select from '@/Components/ui/select';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const dayOptions = [
    { value: '0', label: 'Sunday' },
    { value: '1', label: 'Monday' },
    { value: '2', label: 'Tuesday' },
    { value: '3', label: 'Wednesday' },
    { value: '4', label: 'Thursday' },
    { value: '5', label: 'Friday' },
    { value: '6', label: 'Saturday' },
];

export default function Edit({ rule, zonesByWarehouse, categories }) {
    const can = useCan();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        warehouse_id: rule.warehouse_id,
        scope_type: rule.scope_type,
        scope_id: rule.scope_id ?? '',
        frequency: rule.frequency,
        day_of_week: rule.day_of_week !== null ? String(rule.day_of_week) : '1',
        day_of_month: rule.day_of_month !== null ? String(rule.day_of_month) : '1',
        blind_count: rule.blind_count,
        freeze_mode: rule.freeze_mode,
        is_active: rule.is_active,
    });

    const frequencyOptions = useMemo(
        () => [
            { value: 'daily', label: t('pages.countScheduleRules.frequency.daily') },
            { value: 'weekly', label: t('pages.countScheduleRules.frequency.weekly') },
            { value: 'monthly', label: t('pages.countScheduleRules.frequency.monthly') },
        ],
        [t],
    );

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.count-schedule-rules.update', rule.id));
    };

    const deactivate = () => {
        if (confirm(t('pages.countScheduleRules.confirmDeactivate'))) {
            router.delete(route('admin.count-schedule-rules.destroy', rule.id));
        }
    };

    return (
        <AdminLayout>
            <Head title={t('pages.countScheduleRules.editTitle')} />
            <PageHeader title={t('pages.countScheduleRules.editTitle')}>
                <Link href={route('admin.count-schedule-rules.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>

            <div className="mb-4 text-sm text-rp-text-muted">
                {rule.branch_name} · {rule.warehouse_name}
                {rule.last_run_at && (
                    <span className="ml-3">
                        {t('pages.countScheduleRules.columns.lastRun')}:{' '}
                        {new Date(rule.last_run_at).toLocaleString()}
                    </span>
                )}
            </div>

            <form onSubmit={submit} className="max-w-2xl space-y-5">
                <FormCard>
                    <CountScopeFields
                        data={data}
                        setData={setData}
                        errors={errors}
                        zonesByWarehouse={zonesByWarehouse}
                        categories={categories}
                        t={t}
                    />
                    <AdminFormField label={t('pages.countScheduleRules.fields.frequency')} error={errors.frequency}>
                        <Select
                            options={frequencyOptions}
                            value={data.frequency}
                            onChange={(value) => setData('frequency', value ?? 'weekly')}
                            isSearchable={false}
                        />
                    </AdminFormField>
                    {data.frequency === 'weekly' && (
                        <AdminFormField label={t('pages.countScheduleRules.fields.dayOfWeek')} error={errors.day_of_week}>
                            <Select
                                options={dayOptions}
                                value={data.day_of_week}
                                onChange={(value) => setData('day_of_week', value ?? '1')}
                                isSearchable={false}
                                required
                            />
                        </AdminFormField>
                    )}
                    {data.frequency === 'monthly' && (
                        <AdminFormField
                            label={t('pages.countScheduleRules.fields.dayOfMonth')}
                            error={errors.day_of_month}
                        >
                            <input
                                type="number"
                                min="1"
                                max="31"
                                className="rp-form-input w-full"
                                value={data.day_of_month}
                                onChange={(e) => setData('day_of_month', e.target.value)}
                                required
                            />
                        </AdminFormField>
                    )}
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.blind_count}
                            onChange={(e) => setData('blind_count', e.target.checked)}
                        />
                        {t('pages.countSessions.fields.blindCount')}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.freeze_mode}
                            onChange={(e) => setData('freeze_mode', e.target.checked)}
                        />
                        {t('pages.countSessions.fields.freezeMode')}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        {t('pages.countScheduleRules.active')}
                    </label>
                </FormCard>
                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('pages.countScheduleRules.save')}
                    </button>
                    {can('inventory.cycle-count') && rule.is_active && (
                        <button type="button" onClick={deactivate} className="rp-btn-outline text-red-600">
                            {t('pages.countScheduleRules.deactivate')}
                        </button>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
