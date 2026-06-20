import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import CountScopeFields from '@/Components/admin/CountScopeFields';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
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

function Create({ branches, warehouses, defaultBranchId, zonesByWarehouse, categories }) {
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
        freeze_mode: false,
    });

    const branchOptions = useMemo(
        () =>
            branches.map((branch) => ({
                value: String(branch.id),
                label: branch.name,
            })),
        [branches],
    );

    const warehouseOptions = useMemo(
        () =>
            warehouses.map((warehouse) => ({
                value: String(warehouse.id),
                label: `${warehouse.name} (${warehouse.code})`,
            })),
        [warehouses],
    );

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
                        <Select
                            options={branchOptions}
                            value={String(data.branch_id)}
                            onChange={(value) => setData('branch_id', value)}
                            required
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.inventory.columns.warehouse')}
                        error={errors.warehouse_id}
                    >
                        <Select
                            options={warehouseOptions}
                            value={String(data.warehouse_id)}
                            onChange={(value) => {
                                setData('warehouse_id', value);
                                setData('scope_id', '');
                            }}
                            required
                        />
                    </AdminFormField>
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
                </FormCard>
                <button type="submit" disabled={processing} className="rp-btn-primary">
                    {t('pages.countScheduleRules.save')}
                </button>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
