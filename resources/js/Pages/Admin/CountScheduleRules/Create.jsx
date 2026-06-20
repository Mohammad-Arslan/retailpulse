import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
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

function Create({ branches, warehouses, defaultBranchId }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        branch_id: defaultBranchId ?? branches[0]?.id ?? '',
        warehouse_id: warehouses[0]?.id ?? '',
        scope_type: 'full',
        scope_id: '',
        frequency: 'weekly',
        day_of_week: '1',
        day_of_month: '1',
        blind_count: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.count-schedule-rules.store'));
    };

    return (
        <>
            <Head title={t('pages.countScheduleRules.createTitle')} />
            <PageHeader title={t('pages.countScheduleRules.createTitle')}>
                <Link href={route('admin.count-schedule-rules.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="max-w-2xl space-y-5">
                <FormCard>
                    <AdminFormField label={t('common.branch')} error={errors.branch_id}>
                        <select
                            className="rp-form-input w-full"
                            value={data.branch_id}
                            onChange={(e) => setData('branch_id', e.target.value)}
                            required
                        >
                            {branches.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.inventory.columns.warehouse')}
                        error={errors.warehouse_id}
                    >
                        <select
                            className="rp-form-input w-full"
                            value={data.warehouse_id}
                            onChange={(e) => setData('warehouse_id', e.target.value)}
                            required
                        >
                            {warehouses.map((w) => (
                                <option key={w.id} value={w.id}>
                                    {w.name} ({w.code})
                                </option>
                            ))}
                        </select>
                    </AdminFormField>
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
                </FormCard>
                <button type="submit" disabled={processing} className="rp-btn-primary">
                    {t('pages.countScheduleRules.save')}
                </button>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
