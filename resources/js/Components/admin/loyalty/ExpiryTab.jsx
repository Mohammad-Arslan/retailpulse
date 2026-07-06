import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import Select from '@/Components/ui/select';
import { useCan } from '@/Hooks/useCan';
import { loyaltyExpiryTypeLabel } from '@/lib/loyaltyI18n';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyExpiry() {
    return {
        expiry_type: 'never',
        value: '',
        grace_period_days: '0',
    };
}

export default function ExpiryTab({ program, expiryRules, options }) {
    const { t } = useTranslation();
    const can = useCan();
    const confirm = useConfirm();
    const [editingId, setEditingId] = useState(null);

    const expiryOptions = useMemo(
        () => options.expiryTypes.map((v) => ({ value: v, label: loyaltyExpiryTypeLabel(t, v) })),
        [options.expiryTypes, t],
    );

    const { data, setData, processing, errors, reset } = useForm(emptyExpiry());

    const showValue = data.expiry_type === 'fixed_days' || data.expiry_type === 'fixed_months';

    function openNew() {
        reset();
        setEditingId('new');
    }

    function openEdit(rule) {
        setData({
            expiry_type: rule.expiry_type,
            value: rule.value != null ? String(rule.value) : '',
            grace_period_days: String(rule.grace_period_days ?? 0),
        });
        setEditingId(rule.id);
    }

    function cancel() {
        setEditingId(null);
        reset();
    }

    function submit(e) {
        e.preventDefault();
        const payload = {
            expiry_type: data.expiry_type,
            value: showValue && data.value ? parseInt(data.value, 10) : null,
            grace_period_days: parseInt(data.grace_period_days, 10) || 0,
        };

        if (editingId === 'new') {
            router.post(route('admin.loyalty.programs.expiry-rules.store', program.id), payload, {
                preserveScroll: true,
                onSuccess: cancel,
            });
        } else {
            router.put(route('admin.loyalty.programs.expiry-rules.update', [program.id, editingId]), payload, {
                preserveScroll: true,
                onSuccess: cancel,
            });
        }
    }

    async function destroy(rule) {
        if (!(await confirm({ description: t('pages.loyalty.confirmDelete') }))) return;
        router.delete(route('admin.loyalty.programs.expiry-rules.destroy', [program.id, rule.id]), {
            preserveScroll: true,
        });
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="font-semibold">{t('pages.loyalty.expiry.title')}</h2>
                    <p className="text-sm text-muted-foreground">{t('pages.loyalty.expiry.description')}</p>
                </div>
                {can('loyalty.manage-programs') && editingId === null && expiryRules.length === 0 && (
                    <button type="button" onClick={openNew} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('pages.loyalty.expiry.add')}
                    </button>
                )}
            </div>

            {editingId !== null && can('loyalty.manage-programs') && (
                <FormCard className="max-w-none">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <AdminFormField label={t('pages.loyalty.expiry.fields.expiryType')} error={errors.expiry_type}>
                                <Select options={expiryOptions} value={data.expiry_type} onChange={(v) => setData('expiry_type', v)} />
                            </AdminFormField>
                            {showValue && (
                                <AdminFormField label={t('pages.loyalty.expiry.fields.value')} error={errors.value}>
                                    <input type="number" min="1" className="rp-form-input" value={data.value} onChange={(e) => setData('value', e.target.value)} />
                                </AdminFormField>
                            )}
                            <AdminFormField label={t('pages.loyalty.expiry.fields.gracePeriodDays')} error={errors.grace_period_days}>
                                <input type="number" min="0" className="rp-form-input" value={data.grace_period_days} onChange={(e) => setData('grace_period_days', e.target.value)} />
                            </AdminFormField>
                        </div>
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
                            <th className="px-3 py-2">{t('pages.loyalty.expiry.fields.expiryType')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.expiry.fields.value')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.expiry.fields.gracePeriodDays')}</th>
                            {can('loyalty.manage-programs') && <th className="px-3 py-2" />}
                        </tr>
                    </thead>
                    <tbody>
                        {expiryRules.length === 0 ? (
                            <tr><td colSpan={4} className="px-3 py-6 text-center text-muted-foreground">{t('pages.loyalty.expiry.empty')}</td></tr>
                        ) : (
                            expiryRules.map((rule) => (
                                <tr key={rule.id} className="border-b">
                                    <td className="px-3 py-2">{loyaltyExpiryTypeLabel(t, rule.expiry_type)}</td>
                                    <td className="px-3 py-2">{rule.value ?? '—'}</td>
                                    <td className="px-3 py-2">{rule.grace_period_days}</td>
                                    {can('loyalty.manage-programs') && (
                                        <td className="px-3 py-2 text-right">
                                            <button type="button" onClick={() => openEdit(rule)} className="mr-2 text-teal-600"><Pencil className="inline h-3.5 w-3.5" /></button>
                                            <button type="button" onClick={() => destroy(rule)} className="text-red-600"><Trash2 className="inline h-3.5 w-3.5" /></button>
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
