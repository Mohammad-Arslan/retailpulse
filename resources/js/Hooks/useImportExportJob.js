import { fetchJob } from '@/lib/importExportApi';
import { cumulativeImportProgress, mergeImportProgress } from '@/lib/importProgress';
import { useCallback, useEffect, useRef, useState } from 'react';

const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];

function progressFromJob(job) {
    const phase = job.status === 'validated' ? 'validated' : job.status;

    return {
        phase,
        processed: job.processed_rows ?? 0,
        total: job.total_rows ?? 0,
        success: job.success_rows ?? 0,
        failed: job.failed_rows ?? 0,
        skipped: job.skipped_rows ?? 0,
        errors: job.row_errors_count ?? job.failed_rows ?? 0,
    };
}

export function useImportExportJob(ulid, { onCompleted, onFailed } = {}) {
    const [progress, setProgress] = useState(null);
    const [status, setStatus] = useState('pending');
    const handlersRef = useRef({ onCompleted, onFailed });
    const completedRef = useRef(false);
    const maxPercentRef = useRef(0);

    handlersRef.current = { onCompleted, onFailed };

    const applyProgress = useCallback((incoming, nextStatus) => {
        setProgress((current) => {
            const merged = mergeImportProgress(current, incoming);
            const percent = cumulativeImportProgress(merged, nextStatus ?? merged.phase ?? 'processing');
            maxPercentRef.current = Math.max(maxPercentRef.current, percent);

            if (percent < maxPercentRef.current && nextStatus !== 'completed') {
                return {
                    ...merged,
                    processed: Math.max(Number(current?.processed) || 0, Number(merged?.processed) || 0),
                };
            }

            return merged;
        });
    }, []);

    const applyJob = useCallback((job) => {
        if (!job) {
            return;
        }

        setStatus(job.status);
        applyProgress(progressFromJob(job), job.status);

        if (job.status === 'completed' && !completedRef.current) {
            completedRef.current = true;
            maxPercentRef.current = 100;
            const summary =
                job.summary && typeof job.summary === 'object'
                    ? job.summary
                    : progressFromJob(job);
            handlersRef.current.onCompleted?.(summary);
        }

        if (job.status === 'failed') {
            handlersRef.current.onFailed?.(job);
        }
    }, [applyProgress]);

    const refresh = useCallback(async () => {
        if (!ulid) {
            return null;
        }

        const job = await fetchJob(ulid);
        applyJob(job);

        return job;
    }, [ulid, applyJob]);

    useEffect(() => {
        completedRef.current = false;
        maxPercentRef.current = 0;
    }, [ulid]);

    useEffect(() => {
        if (!ulid || typeof window.Echo === 'undefined') {
            return undefined;
        }

        const channel = window.Echo.private(`import-job.${ulid}`);

        channel.listen('.progress.updated', (payload) => {
            applyProgress(payload, payload.phase ?? 'processing');
            setStatus(payload.phase ?? 'processing');
        });

        channel.listen('.import.completed', (payload) => {
            if (!completedRef.current) {
                completedRef.current = true;
                maxPercentRef.current = 100;
                applyProgress(payload, 'completed');
                setStatus('completed');
                handlersRef.current.onCompleted?.(payload);
            }
        });

        channel.listen('.export.completed', (payload) => {
            if (!completedRef.current) {
                completedRef.current = true;
                maxPercentRef.current = 100;
                applyProgress(payload, 'completed');
                setStatus('completed');
                handlersRef.current.onCompleted?.(payload);
            }
        });

        return () => {
            window.Echo.leave(`import-job.${ulid}`);
        };
    }, [ulid, applyProgress]);

    useEffect(() => {
        if (!ulid) {
            return undefined;
        }

        let active = true;
        let intervalId;

        const poll = async () => {
            try {
                const job = await fetchJob(ulid);

                if (!active || !job) {
                    return;
                }

                applyJob(job);

                if (TERMINAL_STATUSES.includes(job.status) && intervalId) {
                    clearInterval(intervalId);
                }
            } catch {
                // Polling failed — keep last known state.
            }
        };

        poll();
        intervalId = setInterval(poll, 2000);

        return () => {
            active = false;
            clearInterval(intervalId);
        };
    }, [ulid, applyJob]);

    return { progress, status, refresh };
}
