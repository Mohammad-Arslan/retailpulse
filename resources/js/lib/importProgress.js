const VALIDATION_PHASES = ['validating', 'validated'];
const PROCESSING_PHASES = ['processing', 'completing'];

function trackOf(phase) {
    if (VALIDATION_PHASES.includes(phase)) {
        return 'validation';
    }

    if (PROCESSING_PHASES.includes(phase)) {
        return 'processing';
    }

    return phase;
}

/**
 * Validation and processing are one task, not two — every row is read once
 * during validation and written once during processing, so overall progress
 * is how far along *both* passes are together, expressed against the same
 * total row count (not each phase's own 0-100 scale).
 *
 * Validation contributes the first half: 0 rows validated -> 0, all rows
 * validated -> total/2. Processing contributes the second half, starting
 * from a fully-validated baseline: 0 rows processed -> total/2, all rows
 * processed -> total. This is what makes the transition monotonic by
 * construction — there is no point at which "how far through the row set
 * have we gotten" needs to go backwards or restart, so the displayed
 * processed count and the percent bar never appear to reset.
 *
 * `processed`/`total` on the snapshot are always phase-local (how far the
 * *current* phase has gotten through the file) — that's the raw value the
 * backend broadcasts and what mergeImportProgress clamps per phase. This
 * function is the one place that turns that phase-local count into the
 * single combined number the UI shows.
 */
export function combinedProcessed(progress, status) {
    const phase = progress?.phase ?? status;
    const total = Number(progress?.total) || 0;
    const processed = Number(progress?.processed) || 0;

    if (trackOf(phase) === 'validation') {
        return processed / 2;
    }

    if (trackOf(phase) === 'processing') {
        return (total + Math.min(processed, total)) / 2;
    }

    return processed;
}

export function cumulativeImportProgress(progress, status) {
    if (status === 'completed' || status === 'failed' || status === 'cancelled') {
        return 100;
    }

    const total = Number(progress?.total) || 0;
    const phase = progress?.phase ?? status;

    if (total <= 0) {
        return ['validating', 'validated', 'processing', 'completing'].includes(phase) ? 5 : 0;
    }

    const ratio = Math.min(1, combinedProcessed(progress, status) / total);

    return Math.round(ratio * 100);
}

/**
 * Merge an incoming progress snapshot into the current one.
 *
 * Validation and processing each track their own phase-local processed/
 * success/failed/skipped counts (see combinedProcessed above for how those
 * get blended into one displayed task), so a phase change between the two
 * tracks is an intentional reset of those raw counts, not a regression — the
 * incoming snapshot is trusted as-is and the UI labels it by phase.
 *
 * Within the same track, counters must never move backwards: a late or
 * equal-percent snapshot (e.g. a duplicate/out-of-order socket event) is
 * clamped against the current counters instead of overwriting them.
 */
export function mergeImportProgress(current, incoming) {
    if (!incoming) {
        return current;
    }

    if (!current) {
        return incoming;
    }

    const incomingPhase = incoming.phase ?? 'processing';
    const currentPhase = current.phase ?? 'processing';

    if (trackOf(incomingPhase) !== trackOf(currentPhase)) {
        return incoming;
    }

    const currentCombined = combinedProcessed(current, currentPhase);
    const incomingCombined = combinedProcessed(incoming, incomingPhase);

    const merged = {
        ...incoming,
        processed: Math.max(Number(current.processed) || 0, Number(incoming.processed) || 0),
        success: Math.max(Number(current.success) || 0, Number(incoming.success) || 0),
        failed: Math.max(Number(current.failed) || 0, Number(incoming.failed) || 0),
        skipped: Math.max(Number(current.skipped) || 0, Number(incoming.skipped) || 0),
        errors: Math.max(Number(current.errors) || 0, Number(incoming.errors) || 0),
    };

    if (incomingCombined < currentCombined) {
        merged.phase = currentPhase;
    }

    return merged;
}
