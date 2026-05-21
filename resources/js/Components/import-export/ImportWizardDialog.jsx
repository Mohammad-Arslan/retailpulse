import Modal from '@/Components/Modal';
import ImportDataPreview from '@/Components/import-export/ImportDataPreview';
import ImportErrorReport from '@/Components/import-export/ImportErrorReport';
import ImportProgressPanel from '@/Components/import-export/ImportProgressPanel';
import ImportValidationConfig from '@/Components/import-export/ImportValidationConfig';
import ImportWizardStepper from '@/Components/import-export/ImportWizardStepper';
import { useImportJobsTray } from '@/Components/import-export/ImportJobsTray';
import Select from '@/Components/ui/select';
import { useImportExportJob } from '@/Hooks/useImportExportJob';
import {
    cancelJob,
    confirmImport,
    fetchRules,
    jobErrorsUrl,
    saveMapping,
    saveRules,
    uploadImport,
} from '@/lib/importExportApi';
import { FileSpreadsheet } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

const TOTAL_STEPS = 6;

function guessMapping(systemFields, headers) {
    const mapping = {};

    systemFields.forEach((field) => {
        const key = field.key;
        const label = field.label;

        if (headers.includes(key)) {
            mapping[key] = key;

            return;
        }

        if (headers.includes(label)) {
            mapping[key] = label;

            return;
        }

        const match = headers.find(
            (header) =>
                header.toLowerCase() === key.toLowerCase() ||
                header.toLowerCase() === label.toLowerCase(),
        );

        if (match) {
            mapping[key] = match;
        }
    });

    return mapping;
}

function defaultBehaviorOptions(importBehaviors) {
    const options = {};

    importBehaviors.forEach((behavior) => {
        options[behavior.key] = behavior.default ?? false;
    });

    return options;
}

export default function ImportWizardDialog({
    open,
    onClose,
    entityType,
    entityLabel,
    showMatchField = false,
    onJobStarted,
}) {
    const { t } = useTranslation();
    const { refreshJobs } = useImportJobsTray();
    const [step, setStep] = useState(1);
    const [uploading, setUploading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [file, setFile] = useState(null);
    const [filename, setFilename] = useState('');
    const [mode, setMode] = useState('upsert');
    const [matchField, setMatchField] = useState('sku');
    const [isDryRun, setIsDryRun] = useState(false);
    const [ulid, setUlid] = useState(null);
    const [headers, setHeaders] = useState([]);
    const [previewRows, setPreviewRows] = useState([]);
    const [systemFields, setSystemFields] = useState([]);
    const [mapping, setMapping] = useState({});
    const [columnRules, setColumnRules] = useState([]);
    const [availableRules, setAvailableRules] = useState([]);
    const [availableTransforms, setAvailableTransforms] = useState([]);
    const [importBehaviors, setImportBehaviors] = useState([]);
    const [behaviorOptions, setBehaviorOptions] = useState({});
    const [totalRows, setTotalRows] = useState(0);
    const [summary, setSummary] = useState(null);

    const wizardSteps = useMemo(
        () => [
            { key: 'upload', label: t('importExport.steps.upload') },
            { key: 'mapping', label: t('importExport.steps.mapping') },
            { key: 'validation', label: t('importExport.steps.validation') },
            { key: 'review', label: t('importExport.steps.review') },
            { key: 'progress', label: t('importExport.steps.progress') },
            { key: 'result', label: t('importExport.steps.result') },
        ],
        [t],
    );

    const { progress, status } = useImportExportJob(ulid, {
        onCompleted: (payload) => {
            setSummary(payload);
            setStep(6);
            toast.success(t('importExport.importCompleted'));
        },
        onFailed: () => {
            setStep(6);
            toast.error(t('importExport.importFailed'));
        },
    });

    useEffect(() => {
        if (!open) {
            setStep(1);
            setFile(null);
            setFilename('');
            setUlid(null);
            setHeaders([]);
            setPreviewRows([]);
            setSystemFields([]);
            setMapping({});
            setColumnRules([]);
            setAvailableRules([]);
            setAvailableTransforms([]);
            setImportBehaviors([]);
            setBehaviorOptions({});
            setSummary(null);
            setTotalRows(0);
            setMode('upsert');
            setMatchField('sku');
            setIsDryRun(false);
        }
    }, [open]);

    useEffect(() => {
        if (step === 5 && ['completed', 'failed', 'cancelled'].includes(status)) {
            setStep(6);
        }
    }, [step, status]);

    const requiredFields = useMemo(
        () => systemFields.filter((field) => field.required).map((field) => field.key),
        [systemFields],
    );

    const mappingComplete = requiredFields.every((key) => mapping[key]);
    const enabledRuleCount = columnRules.reduce(
        (count, column) => count + (column.rules?.length ?? 0),
        0,
    );

    const handleUpload = async (event) => {
        event.preventDefault();

        if (!file) {
            toast.error(t('importExport.selectFile'));

            return;
        }

        setUploading(true);

        try {
            const data = await uploadImport(entityType, file, mode);
            setUlid(data.ulid);
            setFilename(file.name);
            setHeaders(data.headers ?? []);
            setPreviewRows(data.preview_rows ?? []);
            setTotalRows(data.total_rows ?? data.preview_rows?.length ?? 0);
            setSystemFields(data.system_fields ?? []);
            setMapping(guessMapping(data.system_fields ?? [], data.headers ?? []));
            setStep(2);
            onJobStarted?.(data);
        } catch (error) {
            toast.error(error?.response?.data?.message ?? t('importExport.uploadFailed'));
        } finally {
            setUploading(false);
        }
    };

    const handleMappingNext = async () => {
        if (!mappingComplete) {
            toast.error(t('importExport.mappingIncomplete'));

            return;
        }

        setSubmitting(true);

        try {
            await saveMapping(ulid, mapping);
            const rulesData = await fetchRules(ulid);
            setColumnRules(rulesData.column_rules ?? []);
            setAvailableRules(rulesData.available_rules ?? []);
            setAvailableTransforms(rulesData.available_transforms ?? []);
            const behaviors = rulesData.import_behaviors ?? [];
            setImportBehaviors(behaviors);
            setBehaviorOptions(defaultBehaviorOptions(behaviors));
            setStep(3);
        } catch (error) {
            toast.error(error?.response?.data?.message ?? t('importExport.mappingFailed'));
        } finally {
            setSubmitting(false);
        }
    };

    const handleValidationNext = async () => {
        setSubmitting(true);

        try {
            await saveRules(ulid, {
                column_rules: columnRules,
                save_as_profile: false,
            });
            setStep(4);
        } catch (error) {
            toast.error(error?.response?.data?.message ?? t('importExport.rulesFailed'));
        } finally {
            setSubmitting(false);
        }
    };

    const handleBehaviorChange = (key, value) => {
        setBehaviorOptions((current) => {
            const next = { ...current, [key]: value };

            if (key === 'strict' && value) {
                next.skip_invalid_rows = false;
            }

            if (key === 'skip_invalid_rows' && value) {
                next.strict = false;
            }

            return next;
        });
    };

    const handleConfirm = async () => {
        setSubmitting(true);

        try {
            const options = { ...behaviorOptions };

            if (showMatchField) {
                options.match_field = matchField;
            }

            await confirmImport(ulid, {
                is_dry_run: isDryRun,
                mode,
                options,
            });

            setStep(5);
            onJobStarted?.({ ulid });
        } catch (error) {
            toast.error(error?.response?.data?.message ?? t('importExport.confirmFailed'));
        } finally {
            setSubmitting(false);
        }
    };

    const modalWidth = step >= 5 ? '4xl' : step >= 3 ? '3xl' : '2xl';
    const hasErrors = (summary?.failed ?? 0) > 0;

    const handleClose = async () => {
        if (ulid && step < 5) {
            try {
                await cancelJob(ulid);
                refreshJobs();
            } catch {
                // Draft may already be cancelled or processing started.
            }
        }

        onClose();
    };

    return (
        <Modal show={open} onClose={handleClose} maxWidth={modalWidth}>
            <div className="border-b border-rp-border px-6 py-4">
                <h2 className="text-lg font-semibold text-rp-text">
                    {t('importExport.importTitle', { entity: entityLabel })}
                </h2>
                <p className="mt-1 text-sm text-rp-text-muted">
                    {t('importExport.stepOf', { step, total: TOTAL_STEPS })}
                </p>
            </div>

            <ImportWizardStepper steps={wizardSteps} currentStep={step} />

            <div className="max-h-[65vh] space-y-5 overflow-y-auto px-6 py-5">
                {step === 1 && (
                    <form onSubmit={handleUpload} className="space-y-5">
                        <div className="rounded-lg border border-rp-border bg-rp-surface-subtle px-4 py-3 text-sm text-rp-text-secondary">
                            {t('importExport.uploadIntro')}
                        </div>
                        <div>
                            <label className="rp-form-label">{t('importExport.file')}</label>
                            <input
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                className="rp-form-input mt-1 w-full"
                                onChange={(event) => {
                                    const selected = event.target.files?.[0] ?? null;
                                    setFile(selected);
                                    setFilename(selected?.name ?? '');
                                }}
                            />
                        </div>
                        <div>
                            <label className="rp-form-label">{t('importExport.mode')}</label>
                            <Select
                                className="mt-1"
                                value={mode}
                                onChange={setMode}
                                options={[
                                    { value: 'create', label: t('importExport.modes.create') },
                                    { value: 'update', label: t('importExport.modes.update') },
                                    { value: 'upsert', label: t('importExport.modes.upsert') },
                                ]}
                            />
                            <p className="mt-1 text-xs text-rp-text-muted">{t('importExport.modeHint')}</p>
                        </div>
                        {showMatchField && (
                            <div>
                                <label className="rp-form-label">
                                    {t('importExport.matchField')}
                                </label>
                                <Select
                                    className="mt-1"
                                    value={matchField}
                                    onChange={setMatchField}
                                    options={[
                                        { value: 'sku', label: 'SKU' },
                                        { value: 'barcode', label: t('importExport.barcode') },
                                    ]}
                                />
                            </div>
                        )}
                        <WizardActions>
                            <button type="button" className="rp-btn-outline" onClick={handleClose}>
                                {t('confirm.cancel')}
                            </button>
                            <button type="submit" className="rp-btn-primary" disabled={uploading}>
                                {uploading ? t('importExport.uploading') : t('common.continue')}
                            </button>
                        </WizardActions>
                    </form>
                )}

                {step === 2 && (
                    <div className="space-y-5">
                        <FileInfoCard filename={filename} headers={headers} rows={previewRows} />
                        <ImportDataPreview headers={headers} rows={previewRows} filename={filename} />
                        <section className="space-y-3">
                            <div>
                                <h3 className="text-sm font-semibold text-rp-text">
                                    {t('importExport.columnMapping')}
                                </h3>
                                <p className="text-xs text-rp-text-muted">{t('importExport.columnMappingHint')}</p>
                            </div>
                            <div className="space-y-2">
                                {systemFields.map((field) => (
                                    <div
                                        key={field.key}
                                        className="grid gap-2 rounded-lg border border-rp-border px-3 py-3 sm:grid-cols-2 sm:items-center"
                                    >
                                        <span className="text-sm font-medium text-rp-text">
                                            {field.label}
                                            {field.required && (
                                                <span className="text-destructive"> *</span>
                                            )}
                                        </span>
                                        <Select
                                            value={mapping[field.key] ?? ''}
                                            placeholder={t('importExport.unmapped')}
                                            isClearable
                                            onChange={(value) =>
                                                setMapping((current) => ({
                                                    ...current,
                                                    [field.key]: value ?? '',
                                                }))
                                            }
                                            options={headers.map((header) => ({
                                                value: header,
                                                label: header,
                                            }))}
                                        />
                                    </div>
                                ))}
                            </div>
                        </section>
                        <WizardActions>
                            <button type="button" className="rp-btn-outline" onClick={() => setStep(1)}>
                                {t('importExport.back')}
                            </button>
                            <button
                                type="button"
                                className="rp-btn-primary"
                                disabled={submitting || !mappingComplete}
                                onClick={handleMappingNext}
                            >
                                {submitting ? t('importExport.saving') : t('common.continue')}
                            </button>
                        </WizardActions>
                    </div>
                )}

                {step === 3 && (
                    <div className="space-y-5">
                        <ImportValidationConfig
                            columnRules={columnRules}
                            availableRules={availableRules}
                            availableTransforms={availableTransforms}
                            importBehaviors={importBehaviors}
                            behaviorOptions={behaviorOptions}
                            onColumnRulesChange={setColumnRules}
                            onBehaviorChange={handleBehaviorChange}
                        />
                        <WizardActions>
                            <button type="button" className="rp-btn-outline" onClick={() => setStep(2)}>
                                {t('importExport.back')}
                            </button>
                            <button
                                type="button"
                                className="rp-btn-primary"
                                disabled={submitting}
                                onClick={handleValidationNext}
                            >
                                {submitting ? t('importExport.saving') : t('common.continue')}
                            </button>
                        </WizardActions>
                    </div>
                )}

                {step === 4 && (
                    <div className="space-y-5">
                        <ReviewSection title={t('importExport.review.file')}>
                            <ReviewItem label={t('importExport.file')} value={filename} />
                            <ReviewItem
                                label={t('importExport.mode')}
                                value={t(`importExport.modes.${mode}`)}
                            />
                            <ReviewItem
                                label={t('importExport.review.totalRows')}
                                value={String(totalRows || '—')}
                            />
                        </ReviewSection>

                        <ReviewSection title={t('importExport.review.mapping')}>
                            {systemFields.map((field) => (
                                <ReviewItem
                                    key={field.key}
                                    label={field.label}
                                    value={mapping[field.key] || t('importExport.unmapped')}
                                />
                            ))}
                        </ReviewSection>

                        <ReviewSection title={t('importExport.review.validation')}>
                            <ReviewItem
                                label={t('importExport.review.enabledRules')}
                                value={String(enabledRuleCount)}
                            />
                            {importBehaviors
                                .filter((behavior) => behaviorOptions[behavior.key])
                                .map((behavior) => (
                                    <ReviewItem key={behavior.key} label={behavior.label} value="✓" />
                                ))}
                        </ReviewSection>

                        <label className="flex items-center gap-2 rounded-lg border border-rp-border px-4 py-3 text-sm text-rp-text">
                            <input
                                type="checkbox"
                                className="rounded border-rp-border text-teal-500 focus:ring-teal-500/30"
                                checked={isDryRun}
                                onChange={(event) => setIsDryRun(event.target.checked)}
                            />
                            {t('importExport.dryRun')}
                        </label>

                        <WizardActions>
                            <button type="button" className="rp-btn-outline" onClick={() => setStep(3)}>
                                {t('importExport.back')}
                            </button>
                            <button
                                type="button"
                                className="rp-btn-primary"
                                disabled={submitting}
                                onClick={handleConfirm}
                            >
                                {submitting ? t('importExport.starting') : t('importExport.startImport')}
                            </button>
                        </WizardActions>
                    </div>
                )}

                {step === 5 && (
                    <ImportProgressPanel progress={progress} status={status} />
                )}

                {step === 6 && (
                    <div className="space-y-5">
                        <div className="rounded-lg border border-rp-border bg-rp-surface-subtle px-4 py-4">
                            <p className="font-medium text-rp-text">{t('importExport.summaryTitle')}</p>
                            <ul className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                                <li>{t('importExport.summaryTotal', { count: summary?.total ?? 0 })}</li>
                                <li>{t('importExport.summarySuccess', { count: summary?.success ?? 0 })}</li>
                                <li>{t('importExport.summaryFailed', { count: summary?.failed ?? 0 })}</li>
                                <li>{t('importExport.summarySkipped', { count: summary?.skipped ?? 0 })}</li>
                            </ul>
                        </div>

                        {hasErrors && ulid && (
                            <section className="space-y-3">
                                <div>
                                    <h3 className="text-sm font-semibold text-rp-text">
                                        {t('importExport.errorReport.title')}
                                    </h3>
                                    <p className="text-xs text-rp-text-muted">
                                        {t('importExport.errorReport.subtitle')}
                                    </p>
                                </div>
                                <ImportErrorReport ulid={ulid} />
                            </section>
                        )}

                        <WizardActions>
                            {summary?.error_download_url && (
                                <a href={jobErrorsUrl(ulid)} className="rp-btn-outline inline-flex">
                                    {t('importExport.downloadErrors')}
                                </a>
                            )}
                            <button type="button" className="rp-btn-primary" onClick={handleClose}>
                                {t('importExport.done')}
                            </button>
                        </WizardActions>
                    </div>
                )}
            </div>
        </Modal>
    );
}

function WizardActions({ children }) {
    return <div className="flex justify-end gap-2 border-t border-rp-border pt-4">{children}</div>;
}

function FileInfoCard({ filename, headers, rows }) {
    const { t } = useTranslation();

    return (
        <div className="flex items-start gap-3 rounded-lg border border-rp-border px-4 py-3">
            <FileSpreadsheet className="mt-0.5 h-5 w-5 shrink-0 text-teal-600 dark:text-teal-400" />
            <div>
                <p className="text-sm font-medium text-rp-text">{filename}</p>
                <p className="text-xs text-rp-text-muted">
                    {t('importExport.fileInfo', {
                        columns: headers.length,
                        previewRows: rows.length,
                    })}
                </p>
            </div>
        </div>
    );
}

function ReviewSection({ title, children }) {
    return (
        <section className="rounded-lg border border-rp-border">
            <h3 className="border-b border-rp-border px-4 py-2 text-sm font-semibold text-rp-text">
                {title}
            </h3>
            <div className="divide-y divide-rp-border-subtle">{children}</div>
        </section>
    );
}

function ReviewItem({ label, value }) {
    return (
        <div className="flex items-center justify-between gap-4 px-4 py-2 text-sm">
            <span className="text-rp-text-muted">{label}</span>
            <span className="font-medium text-rp-text">{value}</span>
        </div>
    );
}
