import Modal from '@/Components/Modal';
import { useImportExportJob } from '@/Hooks/useImportExportJob';
import {
    confirmImport,
    fetchRules,
    jobErrorsUrl,
    saveMapping,
    saveRules,
    uploadImport,
} from '@/lib/importExportApi';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

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

export default function ImportWizardDialog({
    open,
    onClose,
    entityType,
    entityLabel,
    showMatchField = false,
    onJobStarted,
}) {
    const { t } = useTranslation();
    const [step, setStep] = useState(1);
    const [uploading, setUploading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [file, setFile] = useState(null);
    const [mode, setMode] = useState('upsert');
    const [matchField, setMatchField] = useState('sku');
    const [isDryRun, setIsDryRun] = useState(false);
    const [strict, setStrict] = useState(false);
    const [ulid, setUlid] = useState(null);
    const [headers, setHeaders] = useState([]);
    const [previewRows, setPreviewRows] = useState([]);
    const [systemFields, setSystemFields] = useState([]);
    const [mapping, setMapping] = useState({});
    const [summary, setSummary] = useState(null);
    const [jobStarted, setJobStarted] = useState(false);

    const { progress, status } = useImportExportJob(ulid, {
        onCompleted: (payload) => {
            setSummary(payload);
            setStep(4);
            toast.success(t('importExport.importCompleted'));
        },
        onFailed: () => {
            toast.error(t('importExport.importFailed'));
        },
    });

    useEffect(() => {
        if (!open) {
            setStep(1);
            setFile(null);
            setUlid(null);
            setHeaders([]);
            setPreviewRows([]);
            setSystemFields([]);
            setMapping({});
            setSummary(null);
            setMode('upsert');
            setMatchField('sku');
            setIsDryRun(false);
            setStrict(false);
            setJobStarted(false);
        }
    }, [open]);

    const requiredFields = useMemo(
        () => systemFields.filter((field) => field.required).map((field) => field.key),
        [systemFields],
    );

    const mappingComplete = requiredFields.every((key) => mapping[key]);

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
            setHeaders(data.headers ?? []);
            setPreviewRows(data.preview_rows ?? []);
            setSystemFields(data.system_fields ?? []);
            const guessed = guessMapping(data.system_fields ?? [], data.headers ?? []);
            setMapping(guessed);
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
            await saveRules(ulid, {
                column_rules: rulesData.column_rules ?? [],
                save_as_profile: false,
            });
            setStep(3);
        } catch (error) {
            toast.error(error?.response?.data?.message ?? t('importExport.mappingFailed'));
        } finally {
            setSubmitting(false);
        }
    };

    const handleConfirm = async () => {
        setSubmitting(true);

        try {
            const options = { strict };

            if (showMatchField) {
                options.match_field = matchField;
            }

            await confirmImport(ulid, {
                is_dry_run: isDryRun,
                mode,
                options,
            });

            setJobStarted(true);
            onJobStarted?.({ ulid });
        } catch (error) {
            toast.error(error?.response?.data?.message ?? t('importExport.confirmFailed'));
        } finally {
            setSubmitting(false);
        }
    };

    const progressPercent = useMemo(() => {
        if (status === 'completed') {
            return 100;
        }

        const total = progress?.total ?? 0;
        const processed = progress?.processed ?? 0;

        if (total <= 0) {
            return status === 'validating' || status === 'processing' ? 5 : 0;
        }

        return Math.min(100, Math.round((processed / total) * 100));
    }, [progress, status]);

    return (
        <Modal show={open} onClose={onClose} maxWidth="2xl">
            <div className="border-b border-ink-200 px-6 py-4 dark:border-ink-700">
                <h2 className="text-lg font-semibold text-rp-text">
                    {t('importExport.importTitle', { entity: entityLabel })}
                </h2>
                <p className="mt-1 text-sm text-rp-text-muted">
                    {t('importExport.stepOf', { step, total: 4 })}
                </p>
            </div>

            <div className="max-h-[70vh] space-y-4 overflow-y-auto px-6 py-5">
                {step === 1 && (
                    <form onSubmit={handleUpload} className="space-y-4">
                        <div>
                            <label className="rp-form-label">{t('importExport.file')}</label>
                            <input
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                className="rp-form-input mt-1 w-full"
                                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                            />
                        </div>
                        <div>
                            <label className="rp-form-label">{t('importExport.mode')}</label>
                            <select
                                className="rp-form-input mt-1 w-full"
                                value={mode}
                                onChange={(event) => setMode(event.target.value)}
                            >
                                <option value="create">{t('importExport.modes.create')}</option>
                                <option value="update">{t('importExport.modes.update')}</option>
                                <option value="upsert">{t('importExport.modes.upsert')}</option>
                            </select>
                        </div>
                        {showMatchField && (
                            <div>
                                <label className="rp-form-label">
                                    {t('importExport.matchField')}
                                </label>
                                <select
                                    className="rp-form-input mt-1 w-full"
                                    value={matchField}
                                    onChange={(event) => setMatchField(event.target.value)}
                                >
                                    <option value="sku">SKU</option>
                                    <option value="barcode">{t('importExport.barcode')}</option>
                                </select>
                            </div>
                        )}
                        <div className="flex justify-end gap-2 pt-2">
                            <button type="button" className="rp-btn-outline" onClick={onClose}>
                                {t('confirm.cancel')}
                            </button>
                            <button type="submit" className="rp-btn-primary" disabled={uploading}>
                                {uploading ? t('importExport.uploading') : t('common.continue')}
                            </button>
                        </div>
                    </form>
                )}

                {step === 2 && (
                    <div className="space-y-4">
                        {previewRows.length > 0 && (
                            <div className="overflow-x-auto rounded-lg border border-ink-200 dark:border-ink-700">
                                <table className="min-w-full text-xs">
                                    <thead className="bg-ink-50 dark:bg-ink-900">
                                        <tr>
                                            {headers.map((header) => (
                                                <th
                                                    key={header}
                                                    className="px-2 py-1.5 text-left font-medium"
                                                >
                                                    {header}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {previewRows.slice(0, 3).map((row, index) => (
                                            <tr key={index} className="border-t border-ink-100">
                                                {headers.map((header) => (
                                                    <td key={header} className="px-2 py-1.5">
                                                        {String(row[header] ?? '')}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <div className="space-y-2">
                            {systemFields.map((field) => (
                                <div
                                    key={field.key}
                                    className="grid gap-2 sm:grid-cols-2 sm:items-center"
                                >
                                    <span className="text-sm font-medium text-rp-text">
                                        {field.label}
                                        {field.required && (
                                            <span className="text-destructive"> *</span>
                                        )}
                                    </span>
                                    <select
                                        className="rp-form-input"
                                        value={mapping[field.key] ?? ''}
                                        onChange={(event) =>
                                            setMapping((current) => ({
                                                ...current,
                                                [field.key]: event.target.value,
                                            }))
                                        }
                                    >
                                        <option value="">{t('importExport.unmapped')}</option>
                                        {headers.map((header) => (
                                            <option key={header} value={header}>
                                                {header}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            ))}
                        </div>
                        <div className="flex justify-end gap-2">
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
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div className="space-y-4">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={isDryRun}
                                onChange={(event) => setIsDryRun(event.target.checked)}
                            />
                            {t('importExport.dryRun')}
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={strict}
                                onChange={(event) => setStrict(event.target.checked)}
                            />
                            {t('importExport.strictMode')}
                        </label>
                        {jobStarted && status !== 'completed' && (
                            <div className="space-y-2">
                                <div className="flex justify-between text-xs text-rp-text-muted">
                                    <span>
                                        {t('importExport.processing')} —{' '}
                                        <span className="capitalize">{status}</span>
                                    </span>
                                    <span>{progressPercent}%</span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                                    <div
                                        className="h-full bg-teal-500 transition-all"
                                        style={{ width: `${progressPercent}%` }}
                                    />
                                </div>
                                {progress && (
                                    <p className="text-xs text-rp-text-muted">
                                        {t('importExport.progressDetail', {
                                            processed: progress.processed ?? 0,
                                            total: progress.total ?? 0,
                                            errors: progress.errors ?? progress.failed ?? 0,
                                        })}
                                    </p>
                                )}
                            </div>
                        )}
                        <div className="flex justify-end gap-2">
                            {!jobStarted && (
                                <button
                                    type="button"
                                    className="rp-btn-primary"
                                    disabled={submitting}
                                    onClick={handleConfirm}
                                >
                                    {submitting
                                        ? t('importExport.starting')
                                        : t('importExport.startImport')}
                                </button>
                            )}
                        </div>
                    </div>
                )}

                {step === 4 && summary && (
                    <div className="space-y-3 text-sm">
                        <p className="font-medium text-rp-text">{t('importExport.summaryTitle')}</p>
                        <ul className="space-y-1 text-rp-text-secondary">
                            <li>{t('importExport.summaryTotal', { count: summary.total ?? 0 })}</li>
                            <li>{t('importExport.summarySuccess', { count: summary.success ?? 0 })}</li>
                            <li>{t('importExport.summaryFailed', { count: summary.failed ?? 0 })}</li>
                            <li>{t('importExport.summarySkipped', { count: summary.skipped ?? 0 })}</li>
                        </ul>
                        {summary.error_download_url && (
                            <a
                                href={jobErrorsUrl(ulid)}
                                className="rp-btn-outline inline-flex"
                            >
                                {t('importExport.downloadErrors')}
                            </a>
                        )}
                        <div className="flex justify-end pt-2">
                            <button type="button" className="rp-btn-primary" onClick={onClose}>
                                {t('importExport.done')}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </Modal>
    );
}
