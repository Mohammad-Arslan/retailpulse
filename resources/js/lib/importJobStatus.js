const RUNNING_STATUSES = ['validating', 'validated', 'processing', 'completing'];

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
