import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import Select from '@/Components/ui/select';
import { useCan } from '@/Hooks/useCan';
import { loyaltyCampaignStatusLabel, loyaltyCampaignTypeLabel } from '@/lib/loyaltyI18n';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyCampaign() {
    return {
        name: '',
        description: '',
        campaign_type: 'double_points',
        multiplier: '2',
        bonus_points: '',
        status: 'draft',
        starts_at: '',
        ends_at: '',
    };
}

export default function CampaignsTab({ program, campaigns, options }) {
    const { t } = useTranslation();
    const can = useCan();
    const confirm = useConfirm();
    const [editingId, setEditingId] = useState(null);

    const typeOptions = useMemo(
        () => options.campaignTypes.map((v) => ({ value: v, label: loyaltyCampaignTypeLabel(t, v) })),
        [options.campaignTypes, t],
    );
    const statusOptions = useMemo(
        () => options.campaignStatuses.map((v) => ({ value: v, label: loyaltyCampaignStatusLabel(t, v) })),
        [options.campaignStatuses, t],
    );

    const { data, setData, processing, errors, reset } = useForm(emptyCampaign());

    function openNew() {
        reset();
        setEditingId('new');
    }

    function openEdit(campaign) {
        const config = campaign.configuration_json ?? {};
        setData({
            name: campaign.name,
            description: campaign.description ?? '',
            campaign_type: campaign.campaign_type,
            multiplier: String(config.multiplier ?? 2),
            bonus_points: config.bonus_points != null ? String(config.bonus_points) : '',
            status: campaign.status,
            starts_at: campaign.starts_at ?? '',
            ends_at: campaign.ends_at ?? '',
        });
        setEditingId(campaign.id);
    }

    function cancel() {
        setEditingId(null);
        reset();
    }

    function submit(e) {
        e.preventDefault();
        const configuration_json = {};
        if (data.campaign_type === 'multiplier') {
            configuration_json.multiplier = parseFloat(data.multiplier) || 1;
        }
        if (data.campaign_type === 'bonus_points' && data.bonus_points) {
            configuration_json.bonus_points = parseInt(data.bonus_points, 10);
        }

        const payload = {
            name: data.name,
            description: data.description || null,
            campaign_type: data.campaign_type,
            configuration_json,
            status: data.status,
            starts_at: data.starts_at || null,
            ends_at: data.ends_at || null,
        };

        if (editingId === 'new') {
            router.post(route('admin.loyalty.programs.campaigns.store', program.id), payload, {
                preserveScroll: true,
                onSuccess: cancel,
            });
        } else {
            router.put(route('admin.loyalty.programs.campaigns.update', [program.id, editingId]), payload, {
                preserveScroll: true,
                onSuccess: cancel,
            });
        }
    }

    async function destroy(campaign) {
        if (!(await confirm({ description: t('pages.loyalty.confirmDelete') }))) return;
        router.delete(route('admin.loyalty.programs.campaigns.destroy', [program.id, campaign.id]), {
            preserveScroll: true,
        });
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="font-semibold">{t('pages.loyalty.campaigns.title')}</h2>
                    <p className="text-sm text-muted-foreground">{t('pages.loyalty.campaigns.description')}</p>
                </div>
                {can('loyalty.manage-campaigns') && editingId === null && (
                    <button type="button" onClick={openNew} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('pages.loyalty.campaigns.add')}
                    </button>
                )}
            </div>

            {editingId !== null && can('loyalty.manage-campaigns') && (
                <FormCard className="max-w-none">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <AdminFormField label={t('pages.loyalty.campaigns.fields.name')} error={errors.name}>
                                <input className="rp-form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.campaigns.fields.campaignType')} error={errors.campaign_type}>
                                <Select options={typeOptions} value={data.campaign_type} onChange={(v) => setData('campaign_type', v)} />
                            </AdminFormField>
                        </div>
                        <AdminFormField label={t('pages.loyalty.campaigns.fields.description')}>
                            <textarea rows={2} className="rp-form-input" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        </AdminFormField>
                        {data.campaign_type === 'multiplier' && (
                            <AdminFormField label={t('pages.loyalty.campaigns.fields.multiplier')}>
                                <input type="number" step="0.01" min="1" className="rp-form-input" value={data.multiplier} onChange={(e) => setData('multiplier', e.target.value)} />
                            </AdminFormField>
                        )}
                        {data.campaign_type === 'bonus_points' && (
                            <AdminFormField label={t('pages.loyalty.campaigns.fields.bonusPoints')}>
                                <input type="number" min="1" className="rp-form-input" value={data.bonus_points} onChange={(e) => setData('bonus_points', e.target.value)} />
                            </AdminFormField>
                        )}
                        <div className="grid gap-4 sm:grid-cols-3">
                            <AdminFormField label={t('pages.loyalty.campaigns.fields.status')} error={errors.status}>
                                <Select options={statusOptions} value={data.status} onChange={(v) => setData('status', v)} />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.campaigns.fields.startsAt')} error={errors.starts_at}>
                                <input type="datetime-local" className="rp-form-input" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.campaigns.fields.endsAt')} error={errors.ends_at}>
                                <input type="datetime-local" className="rp-form-input" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
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
                            <th className="px-3 py-2">{t('pages.loyalty.campaigns.fields.name')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.campaigns.fields.campaignType')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.campaigns.fields.status')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.campaigns.fields.startsAt')}</th>
                            {can('loyalty.manage-campaigns') && <th className="px-3 py-2" />}
                        </tr>
                    </thead>
                    <tbody>
                        {campaigns.length === 0 ? (
                            <tr><td colSpan={5} className="px-3 py-6 text-center text-muted-foreground">{t('pages.loyalty.campaigns.empty')}</td></tr>
                        ) : (
                            campaigns.map((campaign) => (
                                <tr key={campaign.id} className="border-b">
                                    <td className="px-3 py-2 font-medium">{campaign.name}</td>
                                    <td className="px-3 py-2">{loyaltyCampaignTypeLabel(t, campaign.campaign_type)}</td>
                                    <td className="px-3 py-2">{loyaltyCampaignStatusLabel(t, campaign.status)}</td>
                                    <td className="px-3 py-2">{campaign.starts_at ? new Date(campaign.starts_at).toLocaleString() : '—'}</td>
                                    {can('loyalty.manage-campaigns') && (
                                        <td className="px-3 py-2 text-right">
                                            <button type="button" onClick={() => openEdit(campaign)} className="mr-2 text-teal-600"><Pencil className="inline h-3.5 w-3.5" /></button>
                                            <button type="button" onClick={() => destroy(campaign)} className="text-red-600"><Trash2 className="inline h-3.5 w-3.5" /></button>
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
