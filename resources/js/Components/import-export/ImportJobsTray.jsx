import { openExportDownload } from '@/Components/import-export/ImportExportToolbar';
import { fetchJobs } from '@/lib/importExportApi';
import { usePage } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Download, Loader2 } from 'lucide-react';
import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

const ImportJobsContext = createContext({
    trackJob: () => {},
    refreshJobs: () => {},
});

export function useImportJobsTray() {
    return useContext(ImportJobsContext);
}

export function ImportJobsProvider({ children }) {
    const [jobs, setJobs] = useState([]);
    const [open, setOpen] = useState(false);
    const userId = usePage().props.auth?.user?.id;

    const refreshJobs = useCallback(async () => {
        try {
            const list = await fetchJobs();
            setJobs(list);
        } catch {
            // ignore when unauthenticated or API unavailable
        }
    }, []);

    const trackJob = useCallback(
        (payload) => {
            if (payload?.ulid) {
                setOpen(true);
                refreshJobs();
            }
        },
        [refreshJobs],
    );

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

        const interval = setInterval(refreshJobs, open ? 5000 : 15000);

        return () => clearInterval(interval);
    }, [userId, open, refreshJobs]);

    return (
        <ImportJobsContext.Provider value={{ trackJob, refreshJobs }}>
            {children}
            <ImportJobsTrayPanel jobs={jobs} open={open} onOpenChange={setOpen} />
        </ImportJobsContext.Provider>
    );
}

function ImportJobsTrayPanel({ jobs, open, onOpenChange }) {
    const { t } = useTranslation();

    const active = jobs.filter((job) =>
        ['pending', 'validating', 'validated', 'processing', 'completing'].includes(job.status),
    );

    if (jobs.length === 0) {
        return null;
    }

    return (
        <div className="fixed bottom-4 right-4 z-40 w-full max-w-sm">
            <button
                type="button"
                onClick={() => onOpenChange(!open)}
                className="flex w-full items-center justify-between rounded-lg border border-ink-200 bg-white px-4 py-3 text-sm font-medium shadow-lg dark:border-ink-700 dark:bg-ink-900"
            >
                <span className="flex items-center gap-2">
                    {active.length > 0 && (
                        <Loader2 className="h-4 w-4 animate-spin text-teal-500" />
                    )}
                    {t('importExport.jobsTrayTitle', { count: active.length })}
                </span>
                {open ? <ChevronDown className="h-4 w-4" /> : <ChevronUp className="h-4 w-4" />}
            </button>
            {open && (
                <div className="mt-2 max-h-72 overflow-y-auto rounded-lg border border-ink-200 bg-white shadow-xl dark:border-ink-700 dark:bg-ink-900">
                    <ul className="divide-y divide-ink-100 dark:divide-ink-800">
                        {jobs.slice(0, 12).map((job) => (
                            <li key={job.ulid} className="px-4 py-3 text-sm">
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="font-medium capitalize text-rp-text">
                                            {job.type} · {job.entity_type}
                                        </p>
                                        <p className="text-xs text-rp-text-muted">
                                            {job.status}
                                            {job.processed_rows != null && job.total_rows
                                                ? ` · ${job.processed_rows}/${job.total_rows}`
                                                : ''}
                                        </p>
                                    </div>
                                    {job.type === 'export' && job.status === 'completed' && (
                                        <button
                                            type="button"
                                            className="rounded p-1 hover:bg-ink-100 dark:hover:bg-ink-800"
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
