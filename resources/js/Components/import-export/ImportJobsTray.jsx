import { openExportDownload } from '@/Components/import-export/ImportExportToolbar';
import { fetchJobs } from '@/lib/importExportApi';
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronDown, ChevronUp, Download, Loader2, XCircle } from 'lucide-react';
import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

const ACTIVE_STATUSES = ['pending', 'validating', 'validated', 'processing', 'completing'];
const AUTO_DISMISS_MS = 4500;

const ImportJobsContext = createContext({
    trackJob: () => {},
    refreshJobs: () => {},
});

export function useImportJobsTray() {
    return useContext(ImportJobsContext);
}

function jobProgressPercent(job) {
    if (job.status === 'completed' || job.status === 'failed' || job.status === 'cancelled') {
        return 100;
    }

    const total = Number(job.total_rows) || 0;
    const processed = Number(job.processed_rows) || 0;

    if (total <= 0) {
        return ACTIVE_STATUSES.includes(job.status) ? 8 : 0;
    }

    return Math.min(100, Math.round((processed / total) * 100));
}

function JobProgressBar({ job }) {
    const percent = jobProgressPercent(job);
    const isActive = ACTIVE_STATUSES.includes(job.status);
    const isFailed = job.status === 'failed';

    return (
        <div className="mt-2 space-y-1">
            <div className="flex justify-between text-[10px] text-rp-text-muted">
                <span>
                    {job.processed_rows ?? 0} / {job.total_rows ?? 0}
                </span>
                <span>{percent}%</span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800">
                <div
                    className={cn(
                        'h-full transition-all duration-500',
                        isFailed && 'bg-destructive',
                        !isFailed && (job.status === 'completed' || isActive) && 'bg-teal-500',
                    )}
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

export function ImportJobsProvider({ children }) {
    const { t } = useTranslation();
    const [jobs, setJobs] = useState([]);
    const [open, setOpen] = useState(false);
    const [visible, setVisible] = useState(false);
    const userId = usePage().props.auth?.user?.id;
    const hadActiveRef = useRef(false);
    const dismissTimerRef = useRef(null);

    const refreshJobs = useCallback(async () => {
        try {
            const list = await fetchJobs();
            setJobs(list);

            return list;
        } catch {
            return [];
        }
    }, []);

    const scheduleDismiss = useCallback(() => {
        if (dismissTimerRef.current) {
            clearTimeout(dismissTimerRef.current);
        }

        dismissTimerRef.current = setTimeout(() => {
            setOpen(false);
            setVisible(false);
            dismissTimerRef.current = null;
        }, AUTO_DISMISS_MS);
    }, []);

    const trackJob = useCallback(
        (payload) => {
            if (!payload?.ulid) {
                return;
            }

            if (dismissTimerRef.current) {
                clearTimeout(dismissTimerRef.current);
                dismissTimerRef.current = null;
            }

            setVisible(true);
            setOpen(true);
            refreshJobs();
        },
        [refreshJobs],
    );

    useEffect(() => {
        const activeCount = jobs.filter((job) => ACTIVE_STATUSES.includes(job.status)).length;

        if (activeCount > 0) {
            hadActiveRef.current = true;

            if (dismissTimerRef.current) {
                clearTimeout(dismissTimerRef.current);
                dismissTimerRef.current = null;
            }

            setVisible(true);
        } else if (hadActiveRef.current && jobs.length > 0 && visible) {
            const latest = jobs[0];

            if (latest?.status === 'completed') {
                toast.success(t('importExport.trayCompleted', { entity: latest.entity_type }));
            } else if (latest?.status === 'failed') {
                toast.error(t('importExport.trayFailed', { entity: latest.entity_type }));
            }

            scheduleDismiss();
            hadActiveRef.current = false;
        }
    }, [jobs, visible, scheduleDismiss, t]);

    useEffect(() => {
        if (!userId) {
            return;
        }

        refreshJobs();
    }, [userId, refreshJobs]);

    useEffect(() => {
        if (!userId || typeof window.Echo === 'undefined') {
            return undefined;
        }

        const channel = window.Echo.private(`user.${userId}.import-jobs`);

        channel.listen('.progress.updated', () => refreshJobs());
        channel.listen('.import.completed', () => refreshJobs());
        channel.listen('.export.completed', (payload) => {
            refreshJobs();

            if (payload?.download_url) {
                openExportDownload(payload.job_ulid ?? payload.ulid);
            }
        });

        return () => {
            window.Echo.leave(`user.${userId}.import-jobs`);
        };
    }, [userId, refreshJobs]);

    useEffect(() => {
        if (!userId) {
            return undefined;
        }

        const hasActive = jobs.some((job) => ACTIVE_STATUSES.includes(job.status));
        const intervalMs = hasActive || open ? 2000 : 15000;
        const interval = setInterval(refreshJobs, intervalMs);

        return () => clearInterval(interval);
    }, [userId, open, jobs, refreshJobs]);

    useEffect(
        () => () => {
            if (dismissTimerRef.current) {
                clearTimeout(dismissTimerRef.current);
            }
        },
        [],
    );

    return (
        <ImportJobsContext.Provider value={{ trackJob, refreshJobs }}>
            {children}
            {visible && (
                <ImportJobsTrayPanel
                    jobs={jobs}
                    open={open}
                    onOpenChange={setOpen}
                    onDismiss={() => {
                        setOpen(false);
                        setVisible(false);
                    }}
                />
            )}
        </ImportJobsContext.Provider>
    );
}

function ImportJobsTrayPanel({ jobs, open, onOpenChange, onDismiss }) {
    const { t } = useTranslation();

    const active = jobs.filter((job) => ACTIVE_STATUSES.includes(job.status));

    if (jobs.length === 0) {
        return null;
    }

    return (
        <div className="fixed bottom-4 right-4 z-40 w-full max-w-sm">
            <div className="flex items-center gap-1">
                <button
                    type="button"
                    onClick={() => onOpenChange(!open)}
                    className="flex flex-1 items-center justify-between rounded-lg border border-ink-200 bg-white px-4 py-3 text-sm font-medium shadow-lg dark:border-ink-700 dark:bg-ink-900"
                >
                    <span className="flex items-center gap-2">
                        {active.length > 0 ? (
                            <Loader2 className="h-4 w-4 animate-spin text-teal-500" />
                        ) : jobs[0]?.status === 'completed' ? (
                            <CheckCircle2 className="h-4 w-4 text-teal-500" />
                        ) : jobs[0]?.status === 'failed' ? (
                            <XCircle className="h-4 w-4 text-destructive" />
                        ) : null}
                        {t('importExport.jobsTrayTitle', { count: active.length })}
                    </span>
                    {open ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
                </button>
                <button
                    type="button"
                    onClick={onDismiss}
                    className="rounded-lg border border-ink-200 bg-white px-2.5 py-3 text-rp-text-muted shadow-lg hover:text-rp-text dark:border-ink-700 dark:bg-ink-900"
                    aria-label={t('importExport.trayDismiss')}
                >
                    ×
                </button>
            </div>
            {open && (
                <div className="mt-2 max-h-72 overflow-y-auto rounded-lg border border-ink-200 bg-white shadow-xl dark:border-ink-700 dark:bg-ink-900">
                    <ul className="divide-y divide-ink-100 dark:divide-ink-800">
                        {jobs.slice(0, 12).map((job) => (
                            <li key={job.ulid} className="px-4 py-3 text-sm">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium capitalize text-rp-text">
                                            {job.type} · {job.entity_type}
                                        </p>
                                        <p className="text-xs capitalize text-rp-text-muted">
                                            {job.status}
                                            {job.success_rows != null && job.status === 'completed'
                                                ? ` · ${job.success_rows} ${t('importExport.traySuccess')}`
                                                : ''}
                                        </p>
                                        <JobProgressBar job={job} />
                                    </div>
                                    {job.type === 'export' && job.status === 'completed' && (
                                        <button
                                            type="button"
                                            className="shrink-0 rounded p-1 hover:bg-ink-100 dark:hover:bg-ink-800"
                                            onClick={() => openExportDownload(job.ulid)}
                                            title={t('importExport.download')}
                                        >
                                            <Download className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
