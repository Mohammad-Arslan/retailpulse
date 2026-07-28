import { combinedProcessed, cumulativeImportProgress, trackOf } from '@/lib/importProgress';
import { cn } from '@/lib/utils';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

export default function ImportProgressPanel({ progress, status, realtimeUnavailable, onRefresh }) {
    const { t } = useTranslation();

    const percent = useMemo(
        () => cumulativeImportProgress(progress, status),
        [progress, status],
    );

    const phase = progress?.phase ?? status;
    const total = progress?.total ?? 0;
    // Overall progress (the bar + the "X / total" line below) is blended
    // across both validation and processing passes so it never appears to
    // jump backwards at the handoff — see combinedProcessed. The stat cards
    // further down deliberately do NOT use this blended number: they show
    // the *current phase's* raw outcome counts (validated/invalid during
    // validation, successful/failed/skipped during processing), which must
    // stay mutually consistent with each other. Mixing the blended overall
    // count into a "Processed" card next to raw per-phase outcome cards is
    // exactly the bug this component used to have — successful reading
    // larger than processed during validation, and far smaller than
    // processed during processing — so there is no "Processed" stat card
    // here at all, only the phase-appropriate outcome cards.
    const overallProcessed = Math.min(total, Math.round(combinedProcessed(progress, status)));

    const isValidationPhase = trackOf(phase) === 'validation';
    const success = Number(progress?.success) || 0;
    const failed = Number(progress?.failed ?? progress?.errors) || 0;
    const skipped = Number(progress?.skipped) || 0;

    const phaseLabel = {
        validating: t('importExport.phases.validating'),
        validated: t('importExport.phases.validated'),
        processing: t('importExport.phases.processing'),
        completing: t('importExport.phases.completing'),
        completed: t('importExport.phases.completed'),
        failed: t('importExport.phases.failed'),
    }[phase] ?? phase;

    // During validation nothing has been imported yet — rows have only been
    // checked, not written — so label the outcome cards Validated/Invalid
    // rather than claiming a "Successful" import. Once processing starts,
    // these are real imported-row outcomes.
    const successLabel = isValidationPhase
        ? t('importExport.stats.validated')
        : t('importExport.stats.successful');
    const failedLabel = isValidationPhase
        ? t('importExport.stats.invalid')
        : t('importExport.stats.failed');

    return (
        <div className="space-y-5">
            {realtimeUnavailable && (
                <div className="flex items-center justify-between gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
                    <span>{t('importExport.realtimeUnavailable')}</span>
                    {onRefresh && (
                        <button
                            type="button"
                            onClick={onRefresh}
                            className="shrink-0 rounded-md border border-amber-500/40 px-2.5 py-1 text-xs font-medium hover:bg-amber-500/10"
                        >
                            {t('importExport.refreshNow')}
                        </button>
                    )}
                </div>
            )}
            <div className="rounded-lg border border-rp-border bg-rp-surface-subtle px-4 py-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <p className="text-sm font-medium text-rp-text">{phaseLabel}</p>
                        <p className="mt-1 text-xs text-rp-text-muted">
                            {t('importExport.progressRecords', { processed: overallProcessed, total, percent })}
                        </p>
                    </div>
                    <span className="text-2xl font-semibold tabular-nums text-teal-600 dark:text-teal-400">
                        {percent}%
                    </span>
                </div>
                <div className="mt-4 h-2.5 overflow-hidden rounded-full bg-rp-surface-inset">
                    <div
                        className={cn(
                            'h-full rounded-full bg-teal-500 transition-all duration-500 ease-out',
                            status === 'failed' && 'bg-destructive',
                        )}
                        style={{ width: `${percent}%` }}
                    />
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label={t('importExport.stats.total')} value={total} />
                <StatCard label={successLabel} value={success} accent="success" />
                <StatCard
                    label={failedLabel}
                    value={failed}
                    accent={failed > 0 ? 'danger' : undefined}
                />
                {skipped > 0 && (
                    <StatCard label={t('importExport.stats.skipped')} value={skipped} className="sm:col-span-2" />
                )}
            </div>

            <p className="text-xs text-rp-text-muted">{t('importExport.progressHint')}</p>
        </div>
    );
}

function StatCard({ label, value, accent, className }) {
    return (
        <div className={cn('rounded-lg border border-rp-border px-4 py-3', className)}>
            <p className="text-xs text-rp-text-muted">{label}</p>
            <p
                className={cn(
                    'text-lg font-semibold tabular-nums text-rp-text',
                    accent === 'success' && 'text-teal-600 dark:text-teal-400',
                    accent === 'danger' && 'text-destructive',
                )}
            >
                {value}
            </p>
        </div>
    );
}
