import { fetchJob } from '@/lib/importExportApi';
import { cumulativeImportProgress, mergeImportProgress } from '@/lib/importProgress';
import { useCallback, useEffect, useRef, useState } from 'react';

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
    const [realtimeUnavailable, setRealtimeUnavailable] = useState(false);
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

    // WebSocket channel subscription. Reverb is the single source of truth for
    // progress — there is no polling fallback. If Echo isn't available, the UI
    // must say so explicitly rather than sit on a silently frozen bar.
    useEffect(() => {
        if (!ulid) {
            return undefined;
        }

        if (typeof window.Echo === 'undefined') {
            setRealtimeUnavailable(true);
            refresh();

            return undefined;
        }

        setRealtimeUnavailable(false);

        // Sync once immediately via REST, independent of the socket's timing.
        // A small/fast job can validate, process, and broadcast its
        // completion event before the private channel finishes subscribing —
        // most likely on the very first import of a session, when the
        // WebSocket connection itself is still being established — so
        // relying solely on channel.subscribed()'s resync can leave the UI
        // waiting forever on an event that already fired and will never be
        // redelivered.
        refresh();

        const channel = window.Echo.private(`import-job.${ulid}`);

        // Fires on initial subscribe and again on every reconnect, so this both
        // seeds state on mount and resyncs anything missed while the socket was
        // briefly disconnected — no polling loop needed.
        channel.subscribed(() => {
            refresh();
        });

        // A genuine subscription failure (e.g. broadcasting auth rejected)
        // would otherwise leave the UI frozen forever with no live updates
        // and no visible way to recover — surface it the same way as "Echo
        // not loaded" so the manual refresh affordance appears, and try one
        // more REST sync right away.
        channel.error(() => {
            setRealtimeUnavailable(true);
            refresh();
        });

        // Terminal status is sticky: a progress event delivered after
        // completion (out of order, or from a stray reconnect resync) must
        // never revert the job back to a running phase.
        channel.listen('.progress.updated', (payload) => {
            if (completedRef.current) {
                return;
            }

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
    }, [ulid, applyProgress, refresh]);

    return { progress, status, refresh, realtimeUnavailable };
}
