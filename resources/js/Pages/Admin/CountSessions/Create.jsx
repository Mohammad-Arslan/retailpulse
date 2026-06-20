import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import CountScopeFields from '@/Components/admin/CountScopeFields';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Create({ branches, warehouses, defaultBranchId, zonesByWarehouse, categories, varianceDefaults }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        branch_id: defaultBranchId ?? branches[0]?.id ?? '',
        warehouse_id: warehouses[0]?.id ?? '',
        scope_type: 'full',
        scope_id: '',
        blind_count: false,
        freeze_mode: false,
        variance_threshold_pct: varianceDefaults?.pct ?? '',
        variance_threshold_value: varianceDefaults?.value ?? '',
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

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.count-sessions.store'));
    };

    return (
        <>
            <Head title={t('pages.countSessions.createTitle')} />
            <PageHeader title={t('pages.countSessions.createTitle')}>
                <Link href={route('admin.count-sessions.index')} className="rp-btn-outline">
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
                    <AdminFormField label={t('pages.inventory.columns.warehouse')} error={errors.warehouse_id}>
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
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.countSessions.fields.varianceThresholdPct')}
                            error={errors.variance_threshold_pct}
                        >
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className="rp-form-input w-full"
                                value={data.variance_threshold_pct}
                                onChange={(e) => setData('variance_threshold_pct', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.countSessions.fields.varianceThresholdValue')}
                            error={errors.variance_threshold_value}
                        >
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className="rp-form-input w-full"
                                value={data.variance_threshold_value}
                                onChange={(e) => setData('variance_threshold_value', e.target.value)}
                            />
                        </AdminFormField>
                    </div>
                </FormCard>
                <button type="submit" disabled={processing} className="rp-btn-primary">
                    {t('pages.countSessions.createSession')}
                </button>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
