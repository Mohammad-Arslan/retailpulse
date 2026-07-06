import ImportErrorReport from '@/Components/import-export/ImportErrorReport';
import ImportProgressPanel from '@/Components/import-export/ImportProgressPanel';
import Modal from '@/Components/Modal';
import ScrollArea from '@/Components/common/ScrollArea';
import { useImportExportJob } from '@/Hooks/useImportExportJob';
import { fetchJob, fetchLatestImport, jobErrorsUrl } from '@/lib/importExportApi';
import { FileText } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];

function formatTimestamp(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

export default function ImportLogsDialog({
    open,
    onClose,
    entityType,
    entityLabel,
    ulid: preferredUlid = null,
}) {
    const { t } = useTranslation();
    const [loading, setLoading] = useState(false);
    const [job, setJob] = useState(null);
    const [summary, setSummary] = useState(null);

    const activeUlid = job?.ulid ?? preferredUlid ?? null;

    const { progress, status } = useImportExportJob(activeUlid, {
        onCompleted: (payload) => {
            setSummary(payload);
            setJob((current) =>
                current
                    ? {
                          ...current,
                          status: 'completed',
                          summary: payload,
                          success_rows: payload.success ?? current.success_rows,
                          failed_rows: payload.failed ?? current.failed_rows,
                          skipped_rows: payload.skipped ?? current.skipped_rows,
                          total_rows: payload.total ?? current.total_rows,
                      }
                    : current,
            );
        },
        onFailed: () => {
            setJob((current) => (current ? { ...current, status: 'failed' } : current));
        },
    });

    const loadJob = useCallback(async () => {
        if (!open) {
            return;
        }

        setLoading(true);

        try {
            if (preferredUlid) {
                const preferred = await fetchJob(preferredUlid);

                if (
                    preferred &&
                    preferred.entity_type === entityType &&
                    preferred.type === 'import' &&
                    preferred.queued_at
                ) {
                    setJob(preferred);
                    setSummary(
                        preferred.summary && typeof preferred.summary === 'object'
                            ? preferred.summary
                            : null,
                    );

                    return;
                }
            }

            const latest = await fetchLatestImport(entityType);
            setJob(latest);
            setSummary(
                latest?.summary && typeof latest.summary === 'object' ? latest.summary : null,
            );
        } catch {
            setJob(null);
            setSummary(null);
        } finally {
            setLoading(false);
        }
    }, [open, entityType, preferredUlid]);

    useEffect(() => {
        if (!open) {
            setJob(null);
            setSummary(null);

            return;
        }

        loadJob();
    }, [open, preferredUlid, loadJob]);

    const isRunning = job && !TERMINAL_STATUSES.includes(status);
    const resultSummary =
        summary ??
        (job?.summary && typeof job.summary === 'object' ? job.summary : null) ??
        (TERMINAL_STATUSES.includes(status)
            ? {
                  total: job?.total_rows ?? progress?.total ?? 0,
                  success: job?.success_rows ?? progress?.success ?? 0,
                  failed: job?.failed_rows ?? progress?.failed ?? 0,
                  skipped: job?.skipped_rows ?? progress?.skipped ?? 0,
              }
            : null);

    const hasErrors = (resultSummary?.failed ?? job?.row_errors_count ?? 0) > 0;
    const errorDownloadUrl =
        resultSummary?.error_download_url ??
        (job?.output_file_path ? jobErrorsUrl(job.ulid) : null);

    return (
        <Modal show={open} onClose={onClose} maxWidth="4xl">
            <div className="border-b border-rp-border px-6 py-4">
                <div className="flex items-start gap-3">
                    <FileText className="mt-0.5 h-5 w-5 shrink-0 text-teal-600 dark:text-teal-400" />
                    <div>
                        <h2 className="text-lg font-semibold text-rp-text">
                            {t('importExport.logsTitle', { entity: entityLabel })}
                        </h2>
                        <p className="mt-1 text-sm text-rp-text-muted">
                            {t('importExport.logsSubtitle')}
                        </p>
                    </div>
                </div>
            </div>

            <ScrollArea className="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-5">
                {loading && (
                    <p className="text-sm text-rp-text-muted">{t('importExport.logsLoading')}</p>
                )}

                {!loading && !job && (
                    <div className="rounded-lg border border-dashed border-rp-border px-6 py-10 text-center">
                        <p className="text-sm font-medium text-rp-text">
                            {t('importExport.logsEmptyTitle')}
                        </p>
                        <p className="mt-1 text-xs text-rp-text-muted">
                            {t('importExport.logsEmptyDescription')}
                        </p>
                    </div>
                )}

                {!loading && job && (
                    <>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <MetaCard label={t('importExport.logsFile')} value={job.original_filename ?? '—'} />
                            <MetaCard
                                label={t('importExport.logsStatus')}
                                value={t(`importExport.logsStatuses.${job.status}`, {
                                    defaultValue: job.status,
                                })}
                            />
                            <MetaCard label={t('importExport.logsStarted')} value={formatTimestamp(job.started_at ?? job.queued_at)} />
                            <MetaCard label={t('importExport.logsFinished')} value={formatTimestamp(job.completed_at)} />
                        </div>

                        {isRunning && (
                            <ImportProgressPanel progress={progress} status={status} />
                        )}

                        {resultSummary && TERMINAL_STATUSES.includes(status) && (
                            <div className="rounded-lg border border-rp-border bg-rp-surface-subtle px-4 py-4">
                                <p className="font-medium text-rp-text">{t('importExport.summaryTitle')}</p>
                                <ul className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                                    <li>{t('importExport.summaryTotal', { count: resultSummary.total ?? 0 })}</li>
                                    <li>{t('importExport.summarySuccess', { count: resultSummary.success ?? 0 })}</li>
                                    <li>{t('importExport.summaryFailed', { count: resultSummary.failed ?? 0 })}</li>
                                    <li>{t('importExport.summarySkipped', { count: resultSummary.skipped ?? 0 })}</li>
                                </ul>
                            </div>
                        )}

                        {hasErrors && job.ulid && TERMINAL_STATUSES.includes(status) && (
                            <section className="space-y-3">
                                <div>
                                    <h3 className="text-sm font-semibold text-rp-text">
                                        {t('importExport.errorReport.title')}
                                    </h3>
                                    <p className="text-xs text-rp-text-muted">
                                        {t('importExport.errorReport.subtitle')}
                                    </p>
                                </div>
                                <ImportErrorReport ulid={job.ulid} />
                            </section>
                        )}

                        {job.status === 'failed' && job.error_message && (
                            <div className="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                                {job.error_message}
                            </div>
                        )}
                    </>
                )}
            </ScrollArea>

            <div className="flex justify-end gap-2 border-t border-rp-border px-6 py-4">
                {errorDownloadUrl && job?.ulid && TERMINAL_STATUSES.includes(status) && (
                    <a href={errorDownloadUrl} className="rp-btn-outline inline-flex">
                        {t('importExport.downloadErrors')}
                    </a>
                )}
                <button type="button" className="rp-btn-primary" onClick={onClose}>
                    {t('importExport.done')}
                </button>
            </div>
        </Modal>
    );
}

function MetaCard({ label, value }) {
    return (
        <div className="rounded-lg border border-rp-border px-4 py-3">
            <p className="text-xs text-rp-text-muted">{label}</p>
            <p className="mt-1 text-sm font-medium text-rp-text">{value}</p>
        </div>
    );
}
