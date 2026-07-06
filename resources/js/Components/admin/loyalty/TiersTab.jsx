import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import Select from '@/Components/ui/select';
import { useCan } from '@/Hooks/useCan';
import { loyaltyTierQualificationLabel } from '@/lib/loyaltyI18n';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyTier() {
    return {
        name: '',
        tier_level: 1,
        qualification_type: 'points_based',
        qualification_value: '0',
        multiplier: '1',
        status: 'active',
    };
}

export default function TiersTab({ program, tiers, options }) {
    const { t } = useTranslation();
    const can = useCan();
    const confirm = useConfirm();
    const [editingId, setEditingId] = useState(null);

    const qualOptions = useMemo(
        () => options.tierQualificationTypes.map((v) => ({ value: v, label: loyaltyTierQualificationLabel(t, v) })),
        [options.tierQualificationTypes, t],
    );

    const { data, setData, processing, errors, reset } = useForm(emptyTier());

    function openNew() {
        reset();
        setEditingId('new');
    }

    function openEdit(tier) {
        setData({
            name: tier.name,
            tier_level: tier.tier_level,
            qualification_type: tier.qualification_type,
            qualification_value: String(tier.qualification_value),
            multiplier: String(tier.multiplier),
            status: tier.status ?? 'active',
        });
        setEditingId(tier.id);
    }

    function cancel() {
        setEditingId(null);
        reset();
    }

    function submit(e) {
        e.preventDefault();
        const payload = {
            ...data,
            tier_level: parseInt(data.tier_level, 10),
            qualification_value: parseFloat(data.qualification_value),
            multiplier: parseFloat(data.multiplier),
        };

        if (editingId === 'new') {
            router.post(route('admin.loyalty.programs.tiers.store', program.id), payload, {
                preserveScroll: true,
                onSuccess: cancel,
            });
        } else {
            router.put(route('admin.loyalty.programs.tiers.update', [program.id, editingId]), payload, {
                preserveScroll: true,
                onSuccess: cancel,
            });
        }
    }

    async function destroy(tier) {
        if (!(await confirm({ description: t('pages.loyalty.confirmDelete') }))) return;
        router.delete(route('admin.loyalty.programs.tiers.destroy', [program.id, tier.id]), { preserveScroll: true });
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="font-semibold">{t('pages.loyalty.tiers.title')}</h2>
                    <p className="text-sm text-muted-foreground">{t('pages.loyalty.tiers.description')}</p>
                </div>
                {can('loyalty.manage-programs') && editingId === null && (
                    <button type="button" onClick={openNew} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('pages.loyalty.tiers.add')}
                    </button>
                )}
            </div>

            {editingId !== null && can('loyalty.manage-programs') && (
                <FormCard className="max-w-none">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <AdminFormField label={t('pages.loyalty.tiers.fields.name')} error={errors.name}>
                                <input className="rp-form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.tiers.fields.tierLevel')} error={errors.tier_level}>
                                <input type="number" min="1" className="rp-form-input" value={data.tier_level} onChange={(e) => setData('tier_level', e.target.value)} />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.tiers.fields.qualificationType')} error={errors.qualification_type}>
                                <Select options={qualOptions} value={data.qualification_type} onChange={(v) => setData('qualification_type', v)} />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.tiers.fields.qualificationValue')} error={errors.qualification_value}>
                                <input type="number" min="0" step="0.01" className="rp-form-input" value={data.qualification_value} onChange={(e) => setData('qualification_value', e.target.value)} />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.tiers.fields.multiplier')} error={errors.multiplier}>
                                <input type="number" min="0" step="0.01" className="rp-form-input" value={data.multiplier} onChange={(e) => setData('multiplier', e.target.value)} />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.tiers.fields.status')} error={errors.status}>
                                <Select
                                    options={[
                                        { value: 'active', label: t('pages.customerGroups.active') },
                                        { value: 'inactive', label: t('pages.customerGroups.inactive') },
                                    ]}
                                    value={data.status}
                                    onChange={(v) => setData('status', v ?? 'active')}
                                />
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
                            <th className="px-3 py-2">{t('pages.loyalty.tiers.fields.name')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.tiers.fields.tierLevel')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.tiers.fields.qualificationType')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.tiers.fields.multiplier')}</th>
                            {can('loyalty.manage-programs') && <th className="px-3 py-2" />}
                        </tr>
                    </thead>
                    <tbody>
                        {tiers.length === 0 ? (
                            <tr><td colSpan={5} className="px-3 py-6 text-center text-muted-foreground">{t('pages.loyalty.tiers.empty')}</td></tr>
                        ) : (
                            tiers.map((tier) => (
                                <tr key={tier.id} className="border-b">
                                    <td className="px-3 py-2 font-medium">{tier.name}</td>
                                    <td className="px-3 py-2">{tier.tier_level}</td>
                                    <td className="px-3 py-2">{loyaltyTierQualificationLabel(t, tier.qualification_type)} · {tier.qualification_value}</td>
                                    <td className="px-3 py-2">×{tier.multiplier}</td>
                                    {can('loyalty.manage-programs') && (
                                        <td className="px-3 py-2 text-right">
                                            <button type="button" onClick={() => openEdit(tier)} className="mr-2 text-teal-600"><Pencil className="inline h-3.5 w-3.5" /></button>
                                            <button type="button" onClick={() => destroy(tier)} className="text-red-600"><Trash2 className="inline h-3.5 w-3.5" /></button>
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
