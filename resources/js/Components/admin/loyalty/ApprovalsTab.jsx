import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import Select, { mapToSelectOptions } from '@/Components/ui/select';
import { useCan } from '@/Hooks/useCan';
import { loyaltyApprovalActionLabel } from '@/lib/loyaltyI18n';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyPolicy() {
    return {
        action_type: 'manual_adjustment',
        threshold_type: 'points',
        threshold_value: '1000',
        approval_mode: 'pin',
        approver_role_id: '',
        is_active: true,
    };
}

export default function ApprovalsTab({ program, approvalPolicies, options, roles }) {
    const { t } = useTranslation();
    const can = useCan();
    const confirm = useConfirm();
    const [editingId, setEditingId] = useState(null);

    const actionOptions = useMemo(
        () => options.approvalActionTypes.map((v) => ({ value: v, label: loyaltyApprovalActionLabel(t, v) })),
        [options.approvalActionTypes, t],
    );
    const thresholdOptions = useMemo(
        () => options.approvalThresholdTypes.map((v) => ({ value: v, label: t(`pages.loyalty.enums.thresholdTypes.${v}`) })),
        [options.approvalThresholdTypes, t],
    );
    const modeOptions = useMemo(
        () => options.approvalModes.map((v) => ({ value: v, label: t(`pages.loyalty.enums.approvalModes.${v}`) })),
        [options.approvalModes, t],
    );
    const roleOptions = useMemo(() => mapToSelectOptions(roles), [roles]);

    const { data, setData, processing, errors, reset } = useForm(emptyPolicy());

    function openNew() {
        reset();
        setEditingId('new');
    }

    function openEdit(policy) {
        setData({
            action_type: policy.action_type,
            threshold_type: policy.threshold_type,
            threshold_value: String(policy.threshold_value),
            approval_mode: policy.approval_mode,
            approver_role_id: policy.approver_role_id ? String(policy.approver_role_id) : '',
            is_active: policy.is_active,
        });
        setEditingId(policy.id);
    }

    function cancel() {
        setEditingId(null);
        reset();
    }

    function submit(e) {
        e.preventDefault();
        const payload = {
            ...data,
            threshold_value: parseFloat(data.threshold_value),
            approver_role_id: data.approver_role_id ? parseInt(data.approver_role_id, 10) : null,
        };

        if (editingId === 'new') {
            router.post(route('admin.loyalty.programs.approval-policies.store', program.id), payload, {
                preserveScroll: true,
                onSuccess: cancel,
            });
        } else {
            router.put(route('admin.loyalty.programs.approval-policies.update', [program.id, editingId]), payload, {
                preserveScroll: true,
                onSuccess: cancel,
            });
        }
    }

    async function destroy(policy) {
        if (!(await confirm({ description: t('pages.loyalty.confirmDelete') }))) return;
        router.delete(route('admin.loyalty.programs.approval-policies.destroy', [program.id, policy.id]), {
            preserveScroll: true,
        });
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="font-semibold">{t('pages.loyalty.approvals.title')}</h2>
                    <p className="text-sm text-muted-foreground">{t('pages.loyalty.approvals.description')}</p>
                </div>
                {can('loyalty.manage-programs') && editingId === null && (
                    <button type="button" onClick={openNew} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('pages.loyalty.approvals.add')}
                    </button>
                )}
            </div>

            {editingId !== null && can('loyalty.manage-programs') && (
                <FormCard className="max-w-none">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <AdminFormField label={t('pages.loyalty.approvals.fields.actionType')} error={errors.action_type}>
                                <Select options={actionOptions} value={data.action_type} onChange={(v) => setData('action_type', v)} />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.approvals.fields.thresholdType')} error={errors.threshold_type}>
                                <Select options={thresholdOptions} value={data.threshold_type} onChange={(v) => setData('threshold_type', v)} />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.approvals.fields.thresholdValue')} error={errors.threshold_value}>
                                <input type="number" min="0" className="rp-form-input" value={data.threshold_value} onChange={(e) => setData('threshold_value', e.target.value)} />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.approvals.fields.approvalMode')} error={errors.approval_mode}>
                                <Select options={modeOptions} value={data.approval_mode} onChange={(v) => setData('approval_mode', v)} />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.approvals.fields.approverRole')} error={errors.approver_role_id}>
                                <Select options={roleOptions} value={data.approver_role_id} isClearable onChange={(v) => setData('approver_role_id', v ?? '')} />
                            </AdminFormField>
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                            {t('pages.loyalty.approvals.fields.isActive')}
                        </label>
                        <div className="flex gap-2">
                            <button type="submit" disabled={processing} className="rp-btn-primary">{t('pages.loyalty.actions.save')}</button>
                            <button type="button" onClick={cancel} className="rp-btn-outline">{t('pages.loyalty.actions.cancel')}</button>
                        </div>
                    </form>
                </FormCard>
            )}

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-muted/30 text-left text-muted-foreground">
                            <th className="px-3 py-2">{t('pages.loyalty.approvals.fields.actionType')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.approvals.fields.thresholdType')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.approvals.fields.thresholdValue')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.approvals.fields.approvalMode')}</th>
                            {can('loyalty.manage-programs') && <th className="px-3 py-2" />}
                        </tr>
                    </thead>
                    <tbody>
                        {approvalPolicies.length === 0 ? (
                            <tr><td colSpan={5} className="px-3 py-6 text-center text-muted-foreground">{t('pages.loyalty.approvals.empty')}</td></tr>
                        ) : (
                            approvalPolicies.map((policy) => (
                                <tr key={policy.id} className="border-b">
                                    <td className="px-3 py-2">{loyaltyApprovalActionLabel(t, policy.action_type)}</td>
                                    <td className="px-3 py-2">{t(`pages.loyalty.enums.thresholdTypes.${policy.threshold_type}`)}</td>
                                    <td className="px-3 py-2">≥ {policy.threshold_value}</td>
                                    <td className="px-3 py-2">{t(`pages.loyalty.enums.approvalModes.${policy.approval_mode}`)}</td>
                                    {can('loyalty.manage-programs') && (
                                        <td className="px-3 py-2 text-right">
                                            <button type="button" onClick={() => openEdit(policy)} className="mr-2 text-teal-600"><Pencil className="inline h-3.5 w-3.5" /></button>
                                            <button type="button" onClick={() => destroy(policy)} className="text-red-600"><Trash2 className="inline h-3.5 w-3.5" /></button>
                                        </td>
                                    )}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
