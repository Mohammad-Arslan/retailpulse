import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import {
    accountResolutionTypeLabel,
    amountSourceLabel,
    mappingKeyLabel,
    postingRuleEntrySideLabel,
} from '@/lib/accountingI18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function emptyLine(sequence = 1) {
    return {
        sequence,
        entry_side: 'debit',
        account_resolution_type: 'account_mapping',
        account_id: '',
        account_mapping_key: '',
        amount_source: 'gross_amount',
        narration_template: '',
        required: true,
        status: 'active',
    };
}

function Edit({
    ruleSet,
    accounts = [],
    mappingKeys = [],
    resolutionTypes = [],
    amountSources = [],
    entrySides = ['debit', 'credit'],
}) {
    const can = useCan();
    const { t } = useTranslation();

    const initialLines =
        ruleSet.lines?.length > 0
            ? ruleSet.lines.map((line, index) => ({
                  id: line.id ?? null,
                  sequence: line.sequence ?? index + 1,
                  entry_side: line.entry_side ?? 'debit',
                  account_resolution_type: line.account_resolution_type ?? 'account_mapping',
                  account_id: line.account_id ? String(line.account_id) : '',
                  account_mapping_key: line.account_mapping_key ?? '',
                  amount_source: line.amount_source ?? 'gross_amount',
                  narration_template: line.narration_template ?? '',
                  required: line.required !== false,
                  status: line.status ?? 'active',
              }))
            : [emptyLine()];

    const { data, setData, put, processing, errors } = useForm({
        name: ruleSet.name ?? '',
        event_type: ruleSet.event_type ?? '',
        priority: String(ruleSet.priority ?? 100),
        effective_from: ruleSet.effective_from?.slice(0, 10) ?? '',
        effective_to: ruleSet.effective_to?.slice(0, 10) ?? '',
        status: ruleSet.status ?? 'active',
        lines: initialLines,
    });

    const accountOptions = useMemo(
        () =>
            accounts.map((a) => ({
                value: String(a.id),
                label: `${a.code} — ${a.name}`,
            })),
        [accounts],
    );

    const resolutionOptions = useMemo(
        () =>
            resolutionTypes.map((type) => ({
                value: type,
                label: accountResolutionTypeLabel(t, type),
            })),
        [resolutionTypes, t],
    );

    const amountSourceOptions = useMemo(
        () =>
            amountSources.map((source) => ({
                value: source,
                label: amountSourceLabel(t, source),
            })),
        [amountSources, t],
    );

    const entrySideOptions = useMemo(
        () =>
            entrySides.map((side) => ({
                value: side,
                label: postingRuleEntrySideLabel(t, side),
            })),
        [entrySides, t],
    );

    const mappingKeyOptions = useMemo(
        () =>
            mappingKeys.map((key) => ({
                value: key,
                label: mappingKeyLabel(t, key),
            })),
        [mappingKeys, t],
    );

    const updateLine = (index, patch) => {
        setData(
            'lines',
            data.lines.map((line, i) => (i === index ? { ...line, ...patch } : line)),
        );
    };

    const addLine = () => {
        setData('lines', [...data.lines, emptyLine(data.lines.length + 1)]);
    };

    const removeLine = (index) => {
        if (data.lines.length <= 1) {
            return;
        }
        setData(
            'lines',
            data.lines.filter((_, i) => i !== index).map((line, i) => ({ ...line, sequence: i + 1 })),
        );
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.accounting.posting-rules.update', ruleSet.id));
    };

    const readOnly = !can('accounting.manage-posting-rules');

    return (
        <>
            <Head title={t('pages.accounting.postingRules.editTitle', { code: ruleSet.code })} />
            <PageHeader
                title={t('pages.accounting.postingRules.editTitle', { code: ruleSet.code })}
                description={ruleSet.name}
            >
                <Link href={route('admin.accounting.posting-rules.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="space-y-5">
                <FormCard>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField label={t('pages.accounting.postingRules.fields.name')} id="name" error={errors.name}>
                            <input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="rp-form-input"
                                disabled={readOnly}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.postingRules.fields.eventType')}
                            id="event_type"
                            error={errors.event_type}
                        >
                            <input
                                id="event_type"
                                value={data.event_type}
                                onChange={(e) => setData('event_type', e.target.value)}
                                className="rp-form-input font-mono text-sm"
                                disabled={readOnly}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.postingRules.fields.priority')}
                            id="priority"
                            error={errors.priority}
                        >
                            <input
                                id="priority"
                                type="number"
                                value={data.priority}
                                onChange={(e) => setData('priority', e.target.value)}
                                className="rp-form-input"
                                disabled={readOnly}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.postingRules.fields.effectiveFrom')}
                            id="effective_from"
                            error={errors.effective_from}
                        >
                            <input
                                id="effective_from"
                                type="date"
                                value={data.effective_from}
                                onChange={(e) => setData('effective_from', e.target.value)}
                                className="rp-form-input"
                                disabled={readOnly}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.postingRules.fields.effectiveTo')}
                            id="effective_to"
                            error={errors.effective_to}
                        >
                            <input
                                id="effective_to"
                                type="date"
                                value={data.effective_to}
                                onChange={(e) => setData('effective_to', e.target.value)}
                                className="rp-form-input"
                                disabled={readOnly}
                            />
                        </AdminFormField>
                    </div>
                </FormCard>

                <div className="rp-card overflow-hidden">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h3 className="font-semibold">{t('pages.accounting.postingRules.linesTitle')}</h3>
                        {!readOnly && (
                            <Button type="button" variant="outline" size="sm" onClick={addLine}>
                                <Plus className="h-4 w-4" />
                                {t('pages.accounting.postingRules.addLine')}
                            </Button>
                        )}
                    </div>

                    <div className="divide-y">
                        {data.lines.map((line, index) => (
                            <div key={line.id ?? `line-${index}`} className="grid gap-3 p-4 lg:grid-cols-6">
                                <AdminFormField
                                    label={t('pages.accounting.postingRules.lineFields.side')}
                                    id={`line-side-${index}`}
                                    error={errors[`lines.${index}.entry_side`]}
                                >
                                    <Select
                                        value={line.entry_side}
                                        onChange={(value) => updateLine(index, { entry_side: value ?? '' })}
                                        options={entrySideOptions}
                                        disabled={readOnly}
                                    />
                                </AdminFormField>
                                <AdminFormField
                                    label={t('pages.accounting.postingRules.lineFields.resolution')}
                                    id={`line-resolution-${index}`}
                                    error={errors[`lines.${index}.account_resolution_type`]}
                                    className="lg:col-span-2"
                                >
                                    <Select
                                        value={line.account_resolution_type}
                                        onChange={(value) =>
                                            updateLine(index, { account_resolution_type: value ?? '' })
                                        }
                                        options={resolutionOptions}
                                        disabled={readOnly}
                                    />
                                </AdminFormField>
                                {line.account_resolution_type === 'fixed_account' ? (
                                    <AdminFormField
                                        label={t('pages.accounting.postingRules.lineFields.account')}
                                        id={`line-account-${index}`}
                                        error={errors[`lines.${index}.account_id`]}
                                        className="lg:col-span-2"
                                    >
                                        <Select
                                            value={line.account_id}
                                            onChange={(value) => updateLine(index, { account_id: value ?? '' })}
                                            options={[
                                                { value: '', label: '—' },
                                                ...accountOptions,
                                            ]}
                                            disabled={readOnly}
                                        />
                                    </AdminFormField>
                                ) : (
                                    <AdminFormField
                                        label={t('pages.accounting.postingRules.lineFields.mappingKey')}
                                        id={`line-key-${index}`}
                                        error={errors[`lines.${index}.account_mapping_key`]}
                                        className="lg:col-span-2"
                                    >
                                        <Select
                                            value={line.account_mapping_key}
                                            onChange={(value) =>
                                                updateLine(index, { account_mapping_key: value ?? '' })
                                            }
                                            options={[
                                                { value: '', label: '—' },
                                                ...mappingKeyOptions,
                                            ]}
                                            disabled={readOnly}
                                        />
                                    </AdminFormField>
                                )}
                                <AdminFormField
                                    label={t('pages.accounting.postingRules.lineFields.amountSource')}
                                    id={`line-amount-${index}`}
                                    error={errors[`lines.${index}.amount_source`]}
                                >
                                    <Select
                                        value={line.amount_source}
                                        onChange={(value) => updateLine(index, { amount_source: value ?? '' })}
                                        options={amountSourceOptions}
                                        disabled={readOnly}
                                    />
                                </AdminFormField>
                                {!readOnly && (
                                    <div className="flex items-end justify-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => removeLine(index)}
                                            disabled={data.lines.length <= 1}
                                        >
                                            <Trash2 className="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                {!readOnly && (
                    <div className="flex justify-end">
                        <Button type="submit" variant="brand" disabled={processing}>
                            {t('pages.accounting.postingRules.updateSubmit')}
                        </Button>
                    </div>
                )}
            </form>
        </>
    );
}

export default withAdminLayout(Edit);
