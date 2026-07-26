import { describe, expect, it } from 'vitest';
import { applyProgressUpdate, isTerminalJobStatus, isTrayActiveJob } from './importJobStatus';

describe('isTerminalJobStatus', () => {
    it('treats completed, failed, and cancelled as terminal', () => {
        expect(isTerminalJobStatus('completed')).toBe(true);
        expect(isTerminalJobStatus('failed')).toBe(true);
        expect(isTerminalJobStatus('cancelled')).toBe(true);
    });

    it('treats running phases as non-terminal', () => {
        expect(isTerminalJobStatus('processing')).toBe(false);
        expect(isTerminalJobStatus('validating')).toBe(false);
        expect(isTerminalJobStatus('pending')).toBe(false);
    });
});

describe('applyProgressUpdate', () => {
    const runningJob = { ulid: 'job-1', status: 'processing', processed_rows: 10, total_rows: 100 };

    it('applies counters to a running job with a matching ulid', () => {
        const payload = { job_ulid: 'job-1', phase: 'processing', processed: 40, total: 100 };

        const [updated] = applyProgressUpdate([runningJob], payload);

        expect(updated.processed_rows).toBe(40);
        expect(updated.status).toBe('processing');
    });

    it('ignores payloads for a different job', () => {
        const payload = { job_ulid: 'job-2', phase: 'processing', processed: 40 };

        const [updated] = applyProgressUpdate([runningJob], payload);

        expect(updated).toBe(runningJob);
    });

    it('never downgrades a terminal job back to a running phase (the tray-revives-on-late-event regression)', () => {
        const completedJob = { ulid: 'job-1', status: 'completed', processed_rows: 100, total_rows: 100 };
        // A trailing `.progress.updated` delivered after `.import.completed`.
        const payload = { job_ulid: 'job-1', phase: 'processing', processed: 80, total: 100 };

        const [updated] = applyProgressUpdate([completedJob], payload);

        expect(updated).toBe(completedJob);
        expect(updated.status).toBe('completed');
    });

    it('leaves a failed job untouched too', () => {
        const failedJob = { ulid: 'job-1', status: 'failed', processed_rows: 40, total_rows: 100 };
        const payload = { job_ulid: 'job-1', phase: 'processing', processed: 41 };

        const [updated] = applyProgressUpdate([failedJob], payload);

        expect(updated).toBe(failedJob);
    });
});

describe('isTrayActiveJob', () => {
    it('is active for running statuses', () => {
        expect(isTrayActiveJob({ status: 'processing' })).toBe(true);
    });

    it('is active for a confirmed pending job', () => {
        expect(isTrayActiveJob({ status: 'pending', queued_at: '2026-07-26T00:00:00Z' })).toBe(true);
    });

    it('is inactive for a wizard draft (pending, no queued_at)', () => {
        expect(isTrayActiveJob({ status: 'pending', queued_at: null })).toBe(false);
    });

    it('is inactive for terminal statuses', () => {
        expect(isTrayActiveJob({ status: 'completed' })).toBe(false);
    });
});
