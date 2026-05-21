const VALIDATION_WEIGHT = 0.45;

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

export function mergeImportProgress(current, incoming) {
    if (!incoming) {
        return current;
    }

    if (!current) {
        return incoming;
    }

    const currentPercent = cumulativeImportProgress(current, current.phase ?? 'processing');
    const incomingPercent = cumulativeImportProgress(incoming, incoming.phase ?? 'processing');

    if (incomingPercent >= currentPercent) {
        return incoming;
    }

    return {
        ...incoming,
        processed: Math.max(Number(current.processed) || 0, Number(incoming.processed) || 0),
        success: Math.max(Number(current.success) || 0, Number(incoming.success) || 0),
        failed: Math.max(Number(current.failed) || 0, Number(incoming.failed) || 0),
        skipped: Math.max(Number(current.skipped) || 0, Number(incoming.skipped) || 0),
        errors: Math.max(Number(current.errors) || 0, Number(incoming.errors) || 0),
        phase: current.phase ?? incoming.phase,
    };
}
