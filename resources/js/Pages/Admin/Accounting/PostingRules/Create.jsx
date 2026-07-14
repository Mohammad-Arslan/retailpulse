import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
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

function mapSourceLines(source) {
    if (!source?.lines?.length) {
        return [emptyLine()];
    }

    return source.lines.map((line, index) => ({
        sequence: line.sequence ?? index + 1,
        entry_side: line.entry_side ?? 'debit',
        account_resolution_type: line.account_resolution_type ?? 'account_mapping',
        account_id: line.account_id ? String(line.account_id) : '',
        account_mapping_key: line.account_mapping_key ?? '',
        amount_source: line.amount_source ?? 'gross_amount',
        narration_template: line.narration_template ?? '',
        required: line.required !== false,
        status: line.status ?? 'active',
    }));
}

function Create({
    source,
    accounts = [],
    mappingKeys = [],
    resolutionTypes = [],
    amountSources = [],
    entrySides = ['debit', 'credit'],
    branches = [],
    legalEntities = [],
}) {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors, transform } = useForm({
        code: '',
        name: t('pages.accounting.postingRules.nameCopy', { name: source.name ?? '' }),
        duplicate_from_id: source.source_id,
        branch_id: source.branch_id ? String(source.branch_id) : '',
        legal_entity_id: source.legal_entity_id ? String(source.legal_entity_id) : '',
        priority: String(source.priority ?? 100),
        effective_from: source.effective_from?.slice(0, 10) ?? '',
        effective_to: source.effective_to?.slice(0, 10) ?? '',
        status: source.status ?? 'active',
        lines: mapSourceLines(source),
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
            amountSources.map((src) => ({
                value: src,
                label: amountSourceLabel(t, src),
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

    const branchOptions = useMemo(
        () => [
            { value: '', label: t('common.allBranches') },
            ...branches.map((b) => ({ value: String(b.id), label: b.name })),
        ],
        [branches, t],
    );

    const legalEntityOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.postingRules.selectLegalEntity') },
            ...legalEntities.map((entity) => ({
                value: String(entity.id),
                label: entity.legal_name,
            })),
        ],
        [legalEntities, t],
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
        transform((form) => ({
            ...form,
            branch_id: form.branch_id || null,
            legal_entity_id: form.legal_entity_id || null,
            effective_to: form.effective_to || null,
            lines: form.lines.map((line) => ({
                ...line,
                account_id: line.account_id || null,
                account_mapping_key: line.account_mapping_key || null,
            })),
        })).post(route('admin.accounting.posting-rules.store'));
    };

    return (
        <>
            <Head title={t('pages.accounting.postingRules.createTitle')} />
            <PageHeader
                title={t('pages.accounting.postingRules.createTitle')}
                description={t('pages.accounting.postingRules.createDescription', {
                    code: source.code,
                })}
            >
                <Link href={route('admin.accounting.posting-rules.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="space-y-5">
                {errors.lines && typeof errors.lines === 'string' && (
                    <div className="rounded-xl border border-rose-100 bg-rose-100/60 px-4 py-3 text-sm text-rose-600 dark:border-rose-500/30 dark:bg-rose-500/15 dark:text-rose-300">
                        {errors.lines}
                    </div>
                )}

                <FormCard>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.accounting.postingRules.fields.code')}
                            id="code"
                            error={errors.code}
                        >
                            <input
                                id="code"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                className="rp-form-input font-mono text-sm"
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.postingRules.fields.name')}
                            id="name"
                            error={errors.name}
                        >
                            <input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="rp-form-input"
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.postingRules.fields.eventType')}
                            id="event_type"
                        >
                            <input
                                id="event_type"
                                value={source.event_type ?? ''}
                                className="rp-form-input font-mono text-sm"
                                disabled
                                readOnly
                            />
                            <p className="mt-1 text-xs text-rp-text-muted">
                                {t('pages.accounting.postingRules.eventTypeLocked')}
                            </p>
                        </AdminFormField>
                        <AdminFormField
                            label={t('common.branch')}
                            id="branch_id"
                            error={errors.branch_id}
                        >
                            <Select
                                id="branch_id"
                                value={data.branch_id}
                                onChange={(value) => setData('branch_id', value ?? '')}
                                options={branchOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.postingRules.fields.legalEntity')}
                            id="legal_entity_id"
                            error={errors.legal_entity_id}
                        >
                            <Select
                                id="legal_entity_id"
                                value={data.legal_entity_id}
                                onChange={(value) => setData('legal_entity_id', value ?? '')}
                                options={legalEntityOptions}
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
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('common.status')}
                            id="status"
                            error={errors.status}
                        >
                            <Select
                                id="status"
                                value={data.status}
                                onChange={(value) => setData('status', value ?? 'active')}
                                options={[
                                    { value: 'active', label: t('common.active') },
                                    { value: 'inactive', label: t('common.inactive') },
                                ]}
                            />
                        </AdminFormField>
                    </div>
                </FormCard>

                <div className="rp-card overflow-hidden">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h3 className="font-semibold">{t('pages.accounting.postingRules.linesTitle')}</h3>
                        <Button type="button" variant="outline" size="sm" onClick={addLine}>
                            <Plus className="h-4 w-4" />
                            {t('pages.accounting.postingRules.addLine')}
                        </Button>
                    </div>

                    <div className="divide-y">
                        {data.lines.map((line, index) => (
                            <div key={`line-${index}`} className="grid gap-3 p-4 lg:grid-cols-6">
                                <AdminFormField
                                    label={t('pages.accounting.postingRules.lineFields.side')}
                                    id={`line-side-${index}`}
                                    error={errors[`lines.${index}.entry_side`]}
                                >
                                    <Select
                                        value={line.entry_side}
                                        onChange={(value) => updateLine(index, { entry_side: value ?? '' })}
                                        options={entrySideOptions}
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
                                    />
                                </AdminFormField>
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
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex justify-end">
                    <Button type="submit" variant="brand" disabled={processing}>
                        {t('pages.accounting.postingRules.createSubmit')}
                    </Button>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
