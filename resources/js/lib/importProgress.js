const VALIDATION_WEIGHT = 0.45;

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

export function cumulativeImportProgress(progress, status) {
    if (status === 'completed' || status === 'failed' || status === 'cancelled') {
        return 100;
    }

    const total = Number(progress?.total) || 0;
    const processed = Number(progress?.processed) || 0;
    const phase = progress?.phase ?? status;

    if (total <= 0) {
        return ['validating', 'validated', 'processing', 'completing'].includes(phase) ? 5 : 0;
    }

    const ratio = Math.min(1, processed / total);

    if (phase === 'validating' || phase === 'validated') {
        return Math.round(ratio * VALIDATION_WEIGHT * 100);
    }

    if (phase === 'processing' || phase === 'completing') {
        return Math.round((VALIDATION_WEIGHT + ratio * (1 - VALIDATION_WEIGHT)) * 100);
    }

    return Math.round(ratio * 100);
}

/**
 * Merge an incoming progress snapshot into the current one.
 *
 * Validation and processing count different sets of rows, so a phase change
 * between the two tracks is an intentional reset, not a regression — the
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

    const currentPercent = cumulativeImportProgress(current, currentPhase);
    const incomingPercent = cumulativeImportProgress(incoming, incomingPhase);

    const merged = {
        ...incoming,
        processed: Math.max(Number(current.processed) || 0, Number(incoming.processed) || 0),
        success: Math.max(Number(current.success) || 0, Number(incoming.success) || 0),
        failed: Math.max(Number(current.failed) || 0, Number(incoming.failed) || 0),
        skipped: Math.max(Number(current.skipped) || 0, Number(incoming.skipped) || 0),
        errors: Math.max(Number(current.errors) || 0, Number(incoming.errors) || 0),
    };

    if (incomingPercent < currentPercent) {
        merged.phase = currentPhase;
    }

    return merged;
}
