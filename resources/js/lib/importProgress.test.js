import { describe, expect, it } from 'vitest';
import { cumulativeImportProgress, mergeImportProgress } from './importProgress';

describe('cumulativeImportProgress', () => {
    it('weights validation to 0-45%', () => {
        expect(cumulativeImportProgress({ phase: 'validating', processed: 50, total: 100 }, 'validating')).toBe(23);
    });

    it('weights processing to 45-100%', () => {
        expect(cumulativeImportProgress({ phase: 'processing', processed: 50, total: 100 }, 'processing')).toBe(73);
    });

    it('is always 100 for terminal statuses regardless of counters', () => {
        expect(cumulativeImportProgress({ phase: 'processing', processed: 0, total: 100 }, 'completed')).toBe(100);
        expect(cumulativeImportProgress({ phase: 'processing', processed: 0, total: 100 }, 'failed')).toBe(100);
    });
});

describe('mergeImportProgress', () => {
    it('returns incoming when there is no current snapshot', () => {
        const incoming = { phase: 'validating', processed: 1, total: 10 };
        expect(mergeImportProgress(null, incoming)).toBe(incoming);
    });

    it('returns current unchanged when there is no incoming snapshot', () => {
        const current = { phase: 'validating', processed: 1, total: 10 };
        expect(mergeImportProgress(current, null)).toBe(current);
    });

    it('resets counters on an intentional validation -> processing phase change', () => {
        const current = { phase: 'validating', processed: 45, success: 40, failed: 5, skipped: 0, errors: 5, total: 100 };
        const incoming = { phase: 'processing', processed: 0, success: 0, failed: 0, skipped: 0, errors: 0, total: 100 };

        const merged = mergeImportProgress(current, incoming);

        expect(merged.phase).toBe('processing');
        expect(merged.processed).toBe(0);
        expect(merged.success).toBe(0);
    });

    it('does not zero counters on an equal-percent snapshot within the same phase track (the counter-blink regression)', () => {
        // Both snapshots land on exactly the same cumulative percent within the
        // processing track — this used to trigger the `incomingPercent >= currentPercent`
        // wholesale-replace branch and zero the counters back out.
        const current = { phase: 'processing', processed: 50, success: 45, failed: 5, skipped: 0, errors: 5, total: 100 };
        const incoming = { phase: 'processing', processed: 50, success: 0, failed: 0, skipped: 0, errors: 0, total: 100 };

        const merged = mergeImportProgress(current, incoming);

        expect(merged.processed).toBe(50);
        expect(merged.success).toBe(45);
        expect(merged.failed).toBe(5);
    });

    it('clamps a late/out-of-order lower snapshot within the same phase track', () => {
        const current = { phase: 'processing', processed: 80, success: 75, failed: 5, skipped: 0, errors: 5, total: 100 };
        const incoming = { phase: 'processing', processed: 60, success: 55, failed: 5, skipped: 0, errors: 5, total: 100 };

        const merged = mergeImportProgress(current, incoming);

        expect(merged.processed).toBe(80);
        expect(merged.success).toBe(75);
        expect(merged.phase).toBe('processing');
    });

    it('advances normally when the incoming snapshot is genuinely ahead in the same track', () => {
        const current = { phase: 'processing', processed: 50, success: 45, failed: 5, skipped: 0, errors: 5, total: 100 };
        const incoming = { phase: 'processing', processed: 70, success: 65, failed: 5, skipped: 0, errors: 5, total: 100 };

        const merged = mergeImportProgress(current, incoming);

        expect(merged.processed).toBe(70);
        expect(merged.success).toBe(65);
    });
});
