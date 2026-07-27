const RUNNING_STATUSES = ['validating', 'validated', 'processing', 'completing'];
const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];

/**
 * Terminal job status is sticky: once a job has finished, no later progress
 * event (e.g. one delivered out of order after the completion event) may
 * revert it to a running state.
 */
export function isTerminalJobStatus(status) {
    return TERMINAL_STATUSES.includes(status);
}

/**
 * A job is tray-active only once the user confirmed import (queued_at set)
 * or the worker has started processing it. Wizard upload drafts stay hidden.
 */
export function isTrayActiveJob(job) {
    if (!job) {
        return false;
    }

    if (RUNNING_STATUSES.includes(job.status)) {
        return true;
    }

    return job.status === 'pending' && job.queued_at != null;
}

export function filterTrayActiveJobs(jobs) {
    return (jobs ?? []).filter(isTrayActiveJob);
}

/**
 * Apply a `.progress.updated` payload to the tray's job list. A job already
 * in a terminal state is left untouched — this is what keeps a late/
 * out-of-order progress event from reviving a completed/failed/cancelled
 * job in the tray.
 */
export function applyProgressUpdate(jobs, payload) {
    return (jobs ?? []).map((job) => {
        if (job.ulid !== payload?.job_ulid || isTerminalJobStatus(job.status)) {
            return job;
        }

        return {
            ...job,
            processed_rows: payload.processed ?? job.processed_rows,
            total_rows: payload.total ?? job.total_rows,
            success_rows: payload.success ?? job.success_rows,
            failed_rows: payload.failed ?? job.failed_rows,
            status: payload.phase ?? job.status,
        };
    });
}
