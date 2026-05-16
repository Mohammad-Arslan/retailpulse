import { fetchJob } from '@/lib/importExportApi';
import { useCallback, useEffect, useRef, useState } from 'react';

const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];

function progressFromJob(job) {
    return {
        phase: job.status,
        processed: job.processed_rows ?? 0,
        total: job.total_rows ?? 0,
        success: job.success_rows ?? 0,
        failed: job.failed_rows ?? 0,
        skipped: job.skipped_rows ?? 0,
        errors: job.row_errors_count ?? 0,
    };
}

export function useImportExportJob(ulid, { onCompleted, onFailed } = {}) {
    const [progress, setProgress] = useState(null);
    const [status, setStatus] = useState('pending');
    const handlersRef = useRef({ onCompleted, onFailed });
    const completedRef = useRef(false);

    handlersRef.current = { onCompleted, onFailed };

    const applyJob = useCallback((job) => {
        if (!job) {
            return;
        }

        setStatus(job.status);
        setProgress(progressFromJob(job));

        if (job.status === 'completed' && !completedRef.current) {
            completedRef.current = true;
            const summary =
                job.summary && typeof job.summary === 'object'
                    ? job.summary
                    : progressFromJob(job);
            handlersRef.current.onCompleted?.(summary);
        }

        if (job.status === 'failed') {
            handlersRef.current.onFailed?.(job);
        }
    }, []);

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
    }, [ulid]);

    useEffect(() => {
        if (!ulid || typeof window.Echo === 'undefined') {
            return undefined;
        }

        const channel = window.Echo.private(`import-job.${ulid}`);

        channel.listen('.progress.updated', (payload) => {
            setProgress(payload);
            setStatus(payload.phase ?? 'processing');
        });

        channel.listen('.import.completed', (payload) => {
            if (!completedRef.current) {
                completedRef.current = true;
                setProgress(payload);
                setStatus('completed');
                handlersRef.current.onCompleted?.(payload);
            }
        });

        channel.listen('.export.completed', (payload) => {
            if (!completedRef.current) {
                completedRef.current = true;
                setProgress(payload);
                setStatus('completed');
                handlersRef.current.onCompleted?.(payload);
            }
        });

        return () => {
            window.Echo.leave(`import-job.${ulid}`);
        };
    }, [ulid]);

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
