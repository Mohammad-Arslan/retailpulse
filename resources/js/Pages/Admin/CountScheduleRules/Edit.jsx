import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
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

export default function Edit({ rule }) {
    const can = useCan();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        scope_type: rule.scope_type,
        scope_id: rule.scope_id ?? '',
        frequency: rule.frequency,
        day_of_week: rule.day_of_week !== null ? String(rule.day_of_week) : '1',
        day_of_month: rule.day_of_month !== null ? String(rule.day_of_month) : '1',
        blind_count: rule.blind_count,
        is_active: rule.is_active,
    });

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
                    <AdminFormField label={t('pages.countSessions.fields.scope')} error={errors.scope_type}>
                        <select
                            className="rp-form-input w-full"
                            value={data.scope_type}
                            onChange={(e) => setData('scope_type', e.target.value)}
                        >
                            <option value="full">{t('pages.countSessions.scope.full')}</option>
                            <option value="zone">{t('pages.countSessions.scope.zone')}</option>
                            <option value="category">{t('pages.countSessions.scope.category')}</option>
                        </select>
                    </AdminFormField>
                    {data.scope_type !== 'full' && (
                        <AdminFormField
                            label={t('pages.countSessions.fields.scopeId')}
                            error={errors.scope_id}
                        >
                            <input
                                type="number"
                                className="rp-form-input w-full"
                                value={data.scope_id}
                                onChange={(e) => setData('scope_id', e.target.value)}
                                required
                            />
                        </AdminFormField>
                    )}
                    <AdminFormField label={t('pages.countScheduleRules.fields.frequency')} error={errors.frequency}>
                        <select
                            className="rp-form-input w-full"
                            value={data.frequency}
                            onChange={(e) => setData('frequency', e.target.value)}
                        >
                            <option value="daily">{t('pages.countScheduleRules.frequency.daily')}</option>
                            <option value="weekly">{t('pages.countScheduleRules.frequency.weekly')}</option>
                            <option value="monthly">{t('pages.countScheduleRules.frequency.monthly')}</option>
                        </select>
                    </AdminFormField>
                    {data.frequency === 'weekly' && (
                        <AdminFormField label={t('pages.countScheduleRules.fields.dayOfWeek')} error={errors.day_of_week}>
                            <select
                                className="rp-form-input w-full"
                                value={data.day_of_week}
                                onChange={(e) => setData('day_of_week', e.target.value)}
                                required
                            >
                                {dayOptions.map((d) => (
                                    <option key={d.value} value={d.value}>
                                        {d.label}
                                    </option>
                                ))}
                            </select>
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
