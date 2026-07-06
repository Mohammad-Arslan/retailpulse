import { openExportDownload } from '@/Components/import-export/ImportExportToolbar';
import ScrollArea from '@/Components/common/ScrollArea';
import { fetchJobs } from '@/lib/importExportApi';
import { filterTrayActiveJobs, isTrayActiveJob } from '@/lib/importJobStatus';
import { cumulativeImportProgress } from '@/lib/importProgress';
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Loader2 } from 'lucide-react';
import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

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

    return cumulativeImportProgress(
        {
            phase: job.status,
            processed: job.processed_rows ?? 0,
            total: job.total_rows ?? 0,
        },
        job.status,
    );
}

function JobProgressBar({ job }) {
    const percent = jobProgressPercent(job);
    const isActive = isTrayActiveJob(job);
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

    const refreshJobs = useCallback(async () => {
        try {
            const list = await fetchJobs();
            setJobs(list);

            return list;
        } catch {
            return [];
        }
    }, []);

    const trackJob = useCallback(
        (payload) => {
            if (!payload?.ulid) {
                return;
            }

            setVisible(true);
            setOpen(true);
            refreshJobs();
        },
        [refreshJobs],
    );

    useEffect(() => {
        const activeCount = filterTrayActiveJobs(jobs).length;

        if (activeCount > 0) {
            hadActiveRef.current = true;
            setVisible(true);
        } else if (hadActiveRef.current) {
            const latest = jobs.find(
                (job) => job.status === 'completed' || job.status === 'failed',
            );

            if (latest?.status === 'completed') {
                toast.success(t('importExport.trayCompleted', { entity: latest.entity_type }));
            } else if (latest?.status === 'failed') {
                toast.error(t('importExport.trayFailed', { entity: latest.entity_type }));
            }

            hadActiveRef.current = false;
            setOpen(false);
            setVisible(false);
        }
    }, [jobs, t]);

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
                openExportDownload(payload.job_ulid ?? payload.ulid, payload.download_url);
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

        const hasActive = jobs.some(isTrayActiveJob);
        const intervalMs = hasActive || open ? 2000 : 15000;
        const interval = setInterval(refreshJobs, intervalMs);

        return () => clearInterval(interval);
    }, [userId, open, jobs, refreshJobs]);

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

    const active = filterTrayActiveJobs(jobs);

    if (active.length === 0) {
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
                        <Loader2 className="h-4 w-4 animate-spin text-teal-500" />
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
                <ScrollArea className="mt-2 max-h-72 overflow-y-auto rounded-lg border border-ink-200 bg-white shadow-xl dark:border-ink-700 dark:bg-ink-900">
                    <ul className="divide-y divide-ink-100 dark:divide-ink-800">
                        {active.map((job) => (
                            <li key={job.ulid} className="px-4 py-3 text-sm">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium capitalize text-rp-text">
                                            {job.type} · {job.entity_type}
                                        </p>
                                        <p className="text-xs capitalize text-rp-text-muted">
                                            {job.status}
                                        </p>
                                        <JobProgressBar job={job} />
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                </ScrollArea>
            )}
        </div>
    );
}
