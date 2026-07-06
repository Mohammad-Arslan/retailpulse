import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import Select from '@/Components/ui/select';
import { commaStringToIds, idsToCommaString, loyaltyRuleTypeLabel } from '@/lib/loyaltyI18n';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useCan } from '@/Hooks/useCan';

const DAY_OPTIONS = [
    { value: '0', label: 'Sun' },
    { value: '1', label: 'Mon' },
    { value: '2', label: 'Tue' },
    { value: '3', label: 'Wed' },
    { value: '4', label: 'Thu' },
    { value: '5', label: 'Fri' },
    { value: '6', label: 'Sat' },
];

function emptyRule() {
    return {
        name: '',
        description: '',
        rule_type: 'spend_based',
        priority: 100,
        is_active: true,
        effective_from: '',
        effective_to: '',
        spend_amount: '100',
        points: '1',
        bonus_points: '',
        multiplier: '2',
        product_ids: '',
        category_ids: [],
        branch_ids: [],
        day_of_week: [],
        min_redeem_points: '100',
        max_redeem_percent: '50',
        points_per_unit: '100',
        currency_per_unit: '100',
        max_redeem_amount: '',
        match_current_month: false,
    };
}

function ruleToForm(rule) {
    const c = rule.conditions_json ?? {};
    const r = rule.reward_json ?? {};
    return {
        name: rule.name ?? '',
        description: rule.description ?? '',
        rule_type: rule.rule_type ?? 'spend_based',
        priority: rule.priority ?? 100,
        is_active: rule.is_active ?? true,
        effective_from: rule.effective_from ?? '',
        effective_to: rule.effective_to ?? '',
        spend_amount: String(c.spend_amount ?? 100),
        points: String(r.points ?? c.points ?? 1),
        bonus_points: String(r.bonus_points ?? c.bonus_points ?? ''),
        multiplier: String(r.multiplier ?? c.multiplier ?? 2),
        product_ids: idsToCommaString(c.product_ids),
        category_ids: (c.category_ids ?? []).map(String),
        branch_ids: (c.branch_ids ?? []).map(String),
        day_of_week: (c.day_of_week ?? []).map(String),
        min_redeem_points: String(c.min_redeem_points ?? 100),
        max_redeem_percent: String(c.max_redeem_percent ?? 50),
        points_per_unit: String(c.points_per_unit ?? 100),
        currency_per_unit: String(c.currency_per_unit ?? 100),
        max_redeem_amount: c.max_redeem_amount != null ? String(c.max_redeem_amount) : '',
        match_current_month: Boolean(c.match_current_month),
    };
}

function formToPayload(data) {
    const conditions = {};
    const reward = {};
    const type = data.rule_type;

    if (type === 'spend_based') {
        conditions.spend_amount = parseFloat(data.spend_amount) || 100;
        reward.points = parseInt(data.points, 10) || 0;
    } else if (type === 'product_based') {
        conditions.product_ids = commaStringToIds(data.product_ids);
        reward.points = parseInt(data.points, 10) || 0;
    } else if (type === 'category_based') {
        conditions.category_ids = data.category_ids.map((id) => parseInt(id, 10));
        reward.multiplier = parseFloat(data.multiplier) || 1;
    } else if (type === 'branch_based') {
        conditions.branch_ids = data.branch_ids.map((id) => parseInt(id, 10));
        reward.multiplier = parseFloat(data.multiplier) || 1;
    } else if (type === 'time_based') {
        conditions.day_of_week = data.day_of_week.map((d) => parseInt(d, 10));
        reward.multiplier = parseFloat(data.multiplier) || 1;
    } else if (type === 'birthday') {
        conditions.match_current_month = data.match_current_month;
        reward.bonus_points = parseInt(data.bonus_points, 10) || 0;
    } else if (type === 'first_purchase' || type === 'campaign') {
        reward.bonus_points = parseInt(data.bonus_points, 10) || 0;
    } else if (type === 'redemption') {
        conditions.min_redeem_points = parseInt(data.min_redeem_points, 10) || 0;
        conditions.max_redeem_percent = parseFloat(data.max_redeem_percent) || 100;
        conditions.points_per_unit = parseFloat(data.points_per_unit) || 100;
        conditions.currency_per_unit = parseFloat(data.currency_per_unit) || 100;
        if (data.max_redeem_amount) {
            conditions.max_redeem_amount = parseFloat(data.max_redeem_amount);
        }
    }

    return {
        name: data.name,
        description: data.description || null,
        rule_type: data.rule_type,
        priority: parseInt(data.priority, 10),
        is_active: data.is_active,
        effective_from: data.effective_from || null,
        effective_to: data.effective_to || null,
        conditions_json: conditions,
        reward_json: reward,
    };
}

export default function RulesTab({ program, rules, options, branches, categories }) {
    const { t } = useTranslation();
    const can = useCan();
    const confirm = useConfirm();
    const [editingId, setEditingId] = useState(null);

    const ruleTypeOptions = useMemo(
        () => options.ruleTypes.map((v) => ({ value: v, label: loyaltyRuleTypeLabel(t, v) })),
        [options.ruleTypes, t],
    );

    const { data, setData, processing, errors, reset } = useForm(emptyRule());

    function openNew() {
        reset();
        setEditingId('new');
    }

    function openEdit(rule) {
        const form = ruleToForm(rule);
        Object.entries(form).forEach(([k, v]) => setData(k, v));
        setEditingId(rule.id);
    }

    function cancel() {
        setEditingId(null);
        reset();
    }

    function submit(e) {
        e.preventDefault();
        const payload = formToPayload(data);

        if (editingId === 'new') {
            router.post(route('admin.loyalty.programs.rules.store', program.id), payload, {
                preserveScroll: true,
                onSuccess: cancel,
            });
        } else {
            router.put(route('admin.loyalty.programs.rules.update', [program.id, editingId]), payload, {
                preserveScroll: true,
                onSuccess: cancel,
            });
        }
    }

    async function destroy(rule) {
        if (!(await confirm({ description: t('pages.loyalty.confirmDelete') }))) return;
        router.delete(route('admin.loyalty.programs.rules.destroy', [program.id, rule.id]), {
            preserveScroll: true,
        });
    }

    function toggleArray(field, value) {
        const arr = data[field] ?? [];
        setData(field, arr.includes(value) ? arr.filter((v) => v !== value) : [...arr, value]);
    }

    const type = data.rule_type;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="font-semibold">{t('pages.loyalty.rules.title')}</h2>
                    <p className="text-sm text-muted-foreground">{t('pages.loyalty.rules.description')}</p>
                </div>
                {can('loyalty.manage-rules') && editingId === null && (
                    <button type="button" onClick={openNew} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('pages.loyalty.rules.add')}
                    </button>
                )}
            </div>

            {editingId !== null && can('loyalty.manage-rules') && (
                <FormCard className="max-w-none">
                    <h3 className="text-sm font-semibold">
                        {editingId === 'new' ? t('pages.loyalty.rules.add') : t('pages.loyalty.rules.edit')}
                    </h3>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <AdminFormField label={t('pages.loyalty.rules.fields.name')} id="rule_name" error={errors.name}>
                                <input id="rule_name" className="rp-form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.rules.fields.ruleType')} id="rule_type" error={errors.rule_type}>
                                <Select id="rule_type" options={ruleTypeOptions} value={data.rule_type} onChange={(v) => setData('rule_type', v ?? 'spend_based')} />
                            </AdminFormField>
                        </div>
                        <AdminFormField label={t('pages.loyalty.rules.fields.description')} id="rule_desc">
                            <textarea id="rule_desc" rows={2} className="rp-form-input" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        </AdminFormField>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <AdminFormField label={t('pages.loyalty.rules.fields.priority')} id="priority" error={errors.priority}>
                                <input id="priority" type="number" min="1" className="rp-form-input" value={data.priority} onChange={(e) => setData('priority', e.target.value)} />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.rules.fields.effectiveFrom')} id="eff_from">
                                <input id="eff_from" type="datetime-local" className="rp-form-input" value={data.effective_from} onChange={(e) => setData('effective_from', e.target.value)} />
                            </AdminFormField>
                            <AdminFormField label={t('pages.loyalty.rules.fields.effectiveTo')} id="eff_to">
                                <input id="eff_to" type="datetime-local" className="rp-form-input" value={data.effective_to} onChange={(e) => setData('effective_to', e.target.value)} />
                            </AdminFormField>
                        </div>

                        {type === 'spend_based' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <AdminFormField label={t('pages.loyalty.rules.fields.spendAmount')} id="spend_amount">
                                    <input id="spend_amount" type="number" className="rp-form-input" value={data.spend_amount} onChange={(e) => setData('spend_amount', e.target.value)} />
                                </AdminFormField>
                                <AdminFormField label={t('pages.loyalty.rules.fields.points')} id="points">
                                    <input id="points" type="number" className="rp-form-input" value={data.points} onChange={(e) => setData('points', e.target.value)} />
                                </AdminFormField>
                            </div>
                        )}

                        {type === 'product_based' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <AdminFormField label={t('pages.loyalty.rules.fields.productIds')} id="product_ids" hint={t('pages.loyalty.rules.hints.productIds')}>
                                    <input id="product_ids" className="rp-form-input" value={data.product_ids} onChange={(e) => setData('product_ids', e.target.value)} />
                                </AdminFormField>
                                <AdminFormField label={t('pages.loyalty.rules.fields.points')} id="points">
                                    <input id="points" type="number" className="rp-form-input" value={data.points} onChange={(e) => setData('points', e.target.value)} />
                                </AdminFormField>
                            </div>
                        )}

                        {(type === 'category_based' || type === 'branch_based') && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <AdminFormField label={type === 'category_based' ? t('pages.loyalty.rules.fields.categoryIds') : t('pages.loyalty.rules.fields.branchIds')}>
                                    <div className="grid max-h-40 gap-2 overflow-y-auto rounded-lg border p-3 sm:grid-cols-2">
                                        {(type === 'category_based' ? categories : branches).map((item) => (
                                            <label key={item.id} className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={(type === 'category_based' ? data.category_ids : data.branch_ids).includes(String(item.id))}
                                                    onChange={() => toggleArray(type === 'category_based' ? 'category_ids' : 'branch_ids', String(item.id))}
                                                />
                                                {item.name}
                                            </label>
                                        ))}
                                    </div>
                                </AdminFormField>
                                <AdminFormField label={t('pages.loyalty.rules.fields.multiplier')} id="multiplier">
                                    <input id="multiplier" type="number" step="0.01" className="rp-form-input" value={data.multiplier} onChange={(e) => setData('multiplier', e.target.value)} />
                                </AdminFormField>
                            </div>
                        )}

                        {type === 'time_based' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <AdminFormField label={t('pages.loyalty.rules.fields.dayOfWeek')} hint={t('pages.loyalty.rules.hints.dayOfWeek')}>
                                    <div className="flex flex-wrap gap-2">
                                        {DAY_OPTIONS.map((d) => (
                                            <label key={d.value} className="flex items-center gap-1 text-sm">
                                                <input type="checkbox" checked={data.day_of_week.includes(d.value)} onChange={() => toggleArray('day_of_week', d.value)} />
                                                {d.label}
                                            </label>
                                        ))}
                                    </div>
                                </AdminFormField>
                                <AdminFormField label={t('pages.loyalty.rules.fields.multiplier')} id="multiplier">
                                    <input id="multiplier" type="number" step="0.01" className="rp-form-input" value={data.multiplier} onChange={(e) => setData('multiplier', e.target.value)} />
                                </AdminFormField>
                            </div>
                        )}

                        {(type === 'birthday' || type === 'first_purchase' || type === 'campaign') && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                {type === 'birthday' && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input type="checkbox" checked={data.match_current_month} onChange={(e) => setData('match_current_month', e.target.checked)} />
                                        {t('pages.loyalty.rules.fields.matchCurrentMonth')}
                                    </label>
                                )}
                                <AdminFormField label={t('pages.loyalty.rules.fields.bonusPoints')} id="bonus_points">
                                    <input id="bonus_points" type="number" className="rp-form-input" value={data.bonus_points} onChange={(e) => setData('bonus_points', e.target.value)} />
                                </AdminFormField>
                            </div>
                        )}

                        {type === 'redemption' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <AdminFormField label={t('pages.loyalty.rules.fields.minRedeemPoints')} id="min_redeem_points">
                                    <input id="min_redeem_points" type="number" className="rp-form-input" value={data.min_redeem_points} onChange={(e) => setData('min_redeem_points', e.target.value)} />
                                </AdminFormField>
                                <AdminFormField label={t('pages.loyalty.rules.fields.maxRedeemPercent')} id="max_redeem_percent">
                                    <input id="max_redeem_percent" type="number" className="rp-form-input" value={data.max_redeem_percent} onChange={(e) => setData('max_redeem_percent', e.target.value)} />
                                </AdminFormField>
                                <AdminFormField label={t('pages.loyalty.rules.fields.pointsPerUnit')} id="points_per_unit">
                                    <input id="points_per_unit" type="number" className="rp-form-input" value={data.points_per_unit} onChange={(e) => setData('points_per_unit', e.target.value)} />
                                </AdminFormField>
                                <AdminFormField label={t('pages.loyalty.rules.fields.currencyPerUnit')} id="currency_per_unit">
                                    <input id="currency_per_unit" type="number" className="rp-form-input" value={data.currency_per_unit} onChange={(e) => setData('currency_per_unit', e.target.value)} />
                                </AdminFormField>
                                <AdminFormField label={t('pages.loyalty.rules.fields.maxRedeemAmount')} id="max_redeem_amount">
                                    <input id="max_redeem_amount" type="number" className="rp-form-input" value={data.max_redeem_amount} onChange={(e) => setData('max_redeem_amount', e.target.value)} />
                                </AdminFormField>
                            </div>
                        )}

                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                            {t('pages.loyalty.rules.fields.isActive')}
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
                            <th className="px-3 py-2">{t('pages.loyalty.rules.fields.name')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.rules.fields.ruleType')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.rules.fields.priority')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.rules.fields.isActive')}</th>
                            {can('loyalty.manage-rules') && <th className="px-3 py-2" />}
                        </tr>
                    </thead>
                    <tbody>
                        {rules.length === 0 ? (
                            <tr><td colSpan={5} className="px-3 py-6 text-center text-muted-foreground">{t('pages.loyalty.rules.empty')}</td></tr>
                        ) : (
                            rules.map((rule) => (
                                <tr key={rule.id} className="border-b">
                                    <td className="px-3 py-2 font-medium">{rule.name}</td>
                                    <td className="px-3 py-2">{loyaltyRuleTypeLabel(t, rule.rule_type)}</td>
                                    <td className="px-3 py-2">{rule.priority}</td>
                                    <td className="px-3 py-2">{rule.is_active ? t('common.yes') : t('common.no')}</td>
                                    {can('loyalty.manage-rules') && (
                                        <td className="px-3 py-2 text-right">
                                            <button type="button" onClick={() => openEdit(rule)} className="mr-2 text-teal-600 hover:underline"><Pencil className="inline h-3.5 w-3.5" /></button>
                                            <button type="button" onClick={() => destroy(rule)} className="text-red-600 hover:underline"><Trash2 className="inline h-3.5 w-3.5" /></button>
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
