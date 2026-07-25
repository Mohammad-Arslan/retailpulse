import Select from '@/Components/ui/select';
import { cn } from '@/lib/utils';
import { ChevronDown, ChevronUp, Lock, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function ruleKey(rule) {
    if (typeof rule === 'string') {
        return rule;
    }

    return rule?.rule ?? '';
}

function ruleLabel(rule, availableRules) {
    const key = ruleKey(rule);
    const meta = availableRules.find((entry) => entry.rule === key);

    return meta?.label ?? key;
}

function lockedRuleLabel(rule, t) {
    const type = rule?.type ?? '';

    if (type === 'required') {
        return t('importExport.lockedRuleTypes.required');
    }

    if (type === 'string') {
        return t('importExport.lockedRuleTypes.string');
    }

    if (type === 'integer') {
        return t('importExport.lockedRuleTypes.integer');
    }

    if (type === 'numeric') {
        return t('importExport.lockedRuleTypes.numeric');
    }

    if (type === 'boolean') {
        return t('importExport.lockedRuleTypes.boolean');
    }

    if (type === 'date') {
        return t('importExport.lockedRuleTypes.date');
    }

    if (type === 'max') {
        return t('importExport.lockedRuleTypes.max', { value: rule.value });
    }

    if (type === 'in') {
        return t('importExport.lockedRuleTypes.in', {
            values: Array.isArray(rule.value) ? rule.value.join(', ') : rule.value,
        });
    }

    if (type === 'unique') {
        return t('importExport.lockedRuleTypes.uniqueAdvisory');
    }

    return type;
}

function LockedRuleChips({ fieldConstraints, t }) {
    if (!fieldConstraints?.rules?.length) {
        return null;
    }

    return (
        <div className="mb-3">
            <p className="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-rp-text-muted">
                <Lock className="h-3 w-3" aria-hidden />
                {t('importExport.lockedRules')}
            </p>
            <div className="flex flex-wrap gap-2">
                {fieldConstraints.rules.map((rule, index) => {
                    const isUnique = rule.type === 'unique';

                    return (
                        <span
                            key={`${rule.type}-${index}`}
                            className={cn(
                                'inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-medium',
                                isUnique
                                    ? 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200'
                                    : 'border-rp-border bg-rp-surface-inset text-rp-text-secondary',
                            )}
                            title={
                                isUnique
                                    ? t('importExport.lockedUniqueHint')
                                    : t('importExport.lockedRuleHint')
                            }
                        >
                            <Lock className="h-3 w-3 shrink-0 opacity-70" aria-hidden />
                            {lockedRuleLabel(rule, t)}
                        </span>
                    );
                })}
            </div>
            {fieldConstraints.rules.some((rule) => rule.type === 'unique') ? (
                <p className="mt-1.5 text-xs text-rp-text-muted">{t('importExport.lockedUniqueHint')}</p>
            ) : null}
        </div>
    );
}

function ColumnRuleEditor({
    column,
    availableRules,
    availableTransforms,
    lockedConstraints,
    onChange,
}) {
    const { t } = useTranslation();
    const [expanded, setExpanded] = useState(column.is_required ?? false);

    const systemKey = column.system_key ?? column.mapped_to ?? column.column_key;
    const fieldLocked = (lockedConstraints?.fields ?? []).find(
        (entry) => entry.field === systemKey || entry.field === column.column_key,
    );

    const enabledRules = column.rules ?? [];
    const enabledTransforms = Array.isArray(column.transform)
        ? column.transform
        : column.transform
          ? [column.transform]
          : [];

    const addableRules = availableRules.filter(
        (meta) => !enabledRules.some((rule) => ruleKey(rule) === meta.rule),
    );

    const toggleRule = (ruleName, enabled, templateRule = null) => {
        if (enabled) {
            onChange({
                ...column,
                rules: [...enabledRules, templateRule ?? { rule: ruleName }],
            });

            return;
        }

        onChange({
            ...column,
            rules: enabledRules.filter((rule) => ruleKey(rule) !== ruleName),
        });
    };

    const toggleTransform = (transformName, enabled) => {
        const next = enabled
            ? [...enabledTransforms, transformName]
            : enabledTransforms.filter((name) => name !== transformName);

        onChange({ ...column, transform: next });
    };

    const setRequired = (required) => {
        onChange({ ...column, is_required: required });
    };

    return (
        <div className="rounded-lg border border-rp-border">
            <button
                type="button"
                onClick={() => setExpanded((value) => !value)}
                className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
            >
                <div>
                    <p className="text-sm font-medium text-rp-text">
                        {column.display_label ?? column.column_key}
                        {column.is_required ? <span className="text-destructive"> *</span> : null}
                        {fieldLocked ? (
                            <Lock
                                className="ml-1.5 inline h-3.5 w-3.5 text-rp-text-muted"
                                aria-label={t('importExport.lockedRules')}
                            />
                        ) : null}
                    </p>
                    <p className="text-xs text-rp-text-muted">
                        {t('importExport.mappedTo', { column: column.mapped_to ?? column.column_key })}
                        {' · '}
                        {t('importExport.rulesEnabled', { count: enabledRules.length })}
                    </p>
                </div>
                {expanded ? (
                    <ChevronUp className="h-4 w-4 shrink-0 text-rp-text-muted" />
                ) : (
                    <ChevronDown className="h-4 w-4 shrink-0 text-rp-text-muted" />
                )}
            </button>

            {expanded && (
                <div className="space-y-4 border-t border-rp-border px-4 py-4">
                    <LockedRuleChips fieldConstraints={fieldLocked} t={t} />

                    <label className="flex items-center gap-2 text-sm text-rp-text">
                        <input
                            type="checkbox"
                            className="rounded border-rp-border text-teal-500 focus:ring-teal-500/30"
                            checked={Boolean(column.is_required)}
                            onChange={(event) => setRequired(event.target.checked)}
                            disabled={fieldLocked?.rules?.some((rule) => rule.type === 'required')}
                        />
                        {t('importExport.requiredField')}
                    </label>

                    <div>
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-rp-text-muted">
                            {t('importExport.customRules')}
                        </p>
                        {enabledRules.length === 0 ? (
                            <p className="text-xs text-rp-text-muted">{t('importExport.noRulesEnabled')}</p>
                        ) : (
                            <div className="space-y-2">
                                {enabledRules.map((rule, index) => {
                                    const name = ruleKey(rule);
                                    const meta = availableRules.find((entry) => entry.rule === name);
                                    const isLockedEngineRule =
                                        name === 'required' ||
                                        name === 'unique_in_db' ||
                                        (name === 'string' &&
                                            fieldLocked?.rules?.some((r) => r.type === 'string'));

                                    return (
                                        <label
                                            key={`${name}-${index}`}
                                            className={cn(
                                                'flex items-start gap-3 rounded-md border px-3 py-2',
                                                isLockedEngineRule
                                                    ? 'cursor-not-allowed border-rp-border bg-rp-surface-inset opacity-80'
                                                    : 'cursor-pointer border-teal-500/30 bg-teal-500/5',
                                            )}
                                        >
                                            <input
                                                type="checkbox"
                                                className="mt-0.5 rounded border-rp-border text-teal-500 focus:ring-teal-500/30"
                                                checked
                                                disabled={isLockedEngineRule}
                                                onChange={() => {
                                                    if (!isLockedEngineRule) {
                                                        toggleRule(name, false);
                                                    }
                                                }}
                                            />
                                            <span className="min-w-0">
                                                <span className="block text-sm font-medium text-rp-text">
                                                    {meta?.label ?? name}
                                                    {isLockedEngineRule ? (
                                                        <Lock className="ml-1 inline h-3 w-3 opacity-70" />
                                                    ) : null}
                                                </span>
                                                {meta?.description ? (
                                                    <span className="block text-xs text-rp-text-muted">
                                                        {meta.description}
                                                    </span>
                                                ) : null}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    {addableRules.length > 0 && (
                        <div>
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-rp-text-muted">
                                {t('importExport.addRule')}
                            </p>
                            <Select
                                placeholder={t('importExport.selectRule')}
                                value=""
                                onChange={(value) => {
                                    if (value) {
                                        toggleRule(value, true, { rule: value });
                                    }
                                }}
                                options={addableRules.map((meta) => ({
                                    value: meta.rule,
                                    label: meta.label,
                                }))}
                            />
                        </div>
                    )}

                    {availableTransforms.length > 0 && (
                        <div>
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-rp-text-muted">
                                {t('importExport.transforms')}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {availableTransforms.map((transform) => {
                                    const enabled = enabledTransforms.includes(transform.name);

                                    return (
                                        <button
                                            key={transform.name}
                                            type="button"
                                            onClick={() => toggleTransform(transform.name, !enabled)}
                                            className={cn(
                                                'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                                enabled
                                                    ? 'border-teal-500 bg-teal-500/10 text-teal-700 dark:text-teal-300'
                                                    : 'border-rp-border text-rp-text-muted hover:border-rp-border-strong',
                                            )}
                                        >
                                            {transform.label}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {enabledRules.length > 0 && (
                        <div className="rounded-md bg-rp-surface-inset px-3 py-2">
                            <p className="text-xs font-medium text-rp-text-secondary">
                                {t('importExport.activeRules')}
                            </p>
                            <ul className="mt-1 space-y-0.5 text-xs text-rp-text-muted">
                                {enabledRules.map((rule, index) => (
                                    <li key={`${ruleKey(rule)}-${index}`}>
                                        {ruleLabel(rule, availableRules)}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function ImportValidationConfig({
    columnRules,
    lockedConstraints,
    availableRules,
    availableTransforms,
    importBehaviors,
    behaviorOptions,
    onColumnRulesChange,
    onBehaviorChange,
}) {
    const { t } = useTranslation();

    const groupedBehaviors = useMemo(() => {
        const groups = {};

        importBehaviors.forEach((behavior) => {
            const group = behavior.group ?? 'general';
            groups[group] = groups[group] ?? [];
            groups[group].push(behavior);
        });

        return groups;
    }, [importBehaviors]);

    const groupLabels = {
        error_handling: t('importExport.behaviorGroups.errorHandling'),
        transforms: t('importExport.behaviorGroups.transforms'),
        matching: t('importExport.behaviorGroups.matching'),
        validation: t('importExport.behaviorGroups.validation'),
        general: t('importExport.behaviorGroups.general'),
    };

    const updateColumn = (index, nextColumn) => {
        const next = [...columnRules];
        next[index] = nextColumn;
        onColumnRulesChange(next);
    };

    return (
        <div className="space-y-6">
            <div className="rounded-lg border border-teal-500/20 bg-teal-500/5 px-4 py-3">
                <div className="flex items-start gap-3">
                    <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0 text-teal-600 dark:text-teal-400" />
                    <div>
                        <p className="text-sm font-medium text-rp-text">
                            {t('importExport.validationIntroTitle')}
                        </p>
                        <p className="mt-1 text-xs text-rp-text-muted">
                            {t('importExport.validationIntroDescription')}
                        </p>
                    </div>
                </div>
            </div>

            {Object.entries(groupedBehaviors).map(([group, behaviors]) => (
                <section key={group} className="space-y-3">
                    <h3 className="text-sm font-semibold text-rp-text">
                        {groupLabels[group] ?? group}
                    </h3>
                    <div className="space-y-2">
                        {behaviors.map((behavior) => {
                            const checked =
                                behaviorOptions[behavior.key] ?? behavior.default ?? false;
                            const disabled =
                                behavior.key === 'skip_invalid_rows' &&
                                Boolean(behaviorOptions.strict);

                            return (
                                <label
                                    key={behavior.key}
                                    className={cn(
                                        'flex items-start gap-3 rounded-lg border px-4 py-3',
                                        disabled ? 'opacity-60' : 'cursor-pointer',
                                        checked
                                            ? 'border-teal-500/30 bg-teal-500/5'
                                            : 'border-rp-border',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        className="mt-0.5 rounded border-rp-border text-teal-500 focus:ring-teal-500/30"
                                        checked={Boolean(checked)}
                                        disabled={disabled}
                                        onChange={(event) =>
                                            onBehaviorChange(behavior.key, event.target.checked)
                                        }
                                    />
                                    <span>
                                        <span className="block text-sm font-medium text-rp-text">
                                            {behavior.label}
                                        </span>
                                        <span className="block text-xs text-rp-text-muted">
                                            {behavior.description}
                                        </span>
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                </section>
            ))}

            <section className="space-y-3">
                <div>
                    <h3 className="text-sm font-semibold text-rp-text">
                        {t('importExport.columnValidationRules')}
                    </h3>
                    <p className="text-xs text-rp-text-muted">
                        {t('importExport.columnValidationRulesHint')}
                    </p>
                </div>
                <div className="space-y-3">
                    {columnRules.map((column, index) => (
                        <ColumnRuleEditor
                            key={`${column.column_key}-${index}`}
                            column={column}
                            availableRules={availableRules}
                            availableTransforms={availableTransforms}
                            lockedConstraints={lockedConstraints}
                            onChange={(next) => updateColumn(index, next)}
                        />
                    ))}
                </div>
            </section>
        </div>
    );
}
