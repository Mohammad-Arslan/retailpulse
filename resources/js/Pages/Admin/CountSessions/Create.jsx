import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

function Create({ branches, warehouses, defaultBranchId }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        branch_id: defaultBranchId ?? branches[0]?.id ?? '',
        warehouse_id: warehouses[0]?.id ?? '',
        scope_type: 'full',
        scope_id: '',
        blind_count: false,
        freeze_mode: false,
        variance_threshold_pct: '',
        variance_threshold_value: '',
    });

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
                    <AdminFormField label={t('pages.inventory.columns.warehouse')} error={errors.warehouse_id}>
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
                        <AdminFormField label={t('pages.countSessions.fields.scopeId')} error={errors.scope_id}>
                            <input
                                type="number"
                                className="rp-form-input w-full"
                                value={data.scope_id}
                                onChange={(e) => setData('scope_id', e.target.value)}
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
                    {t('pages.countSessions.createSession')}
                </button>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
