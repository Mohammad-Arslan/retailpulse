import { cumulativeImportProgress } from '@/lib/importProgress';
import { cn } from '@/lib/utils';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

export default function ImportProgressPanel({ progress, status }) {
    const { t } = useTranslation();

    const percent = useMemo(
        () => cumulativeImportProgress(progress, status),
        [progress, status],
    );

    const phase = progress?.phase ?? status;
    const total = progress?.total ?? 0;
    const processed = progress?.processed ?? 0;
    const success = progress?.success ?? 0;
    const failed = progress?.failed ?? progress?.errors ?? 0;
    const skipped = progress?.skipped ?? 0;

    const phaseLabel = {
        validating: t('importExport.phases.validating'),
        validated: t('importExport.phases.validated'),
        processing: t('importExport.phases.processing'),
        completing: t('importExport.phases.completing'),
        completed: t('importExport.phases.completed'),
        failed: t('importExport.phases.failed'),
    }[phase] ?? phase;

    return (
        <div className="space-y-5">
            <div className="rounded-lg border border-rp-border bg-rp-surface-subtle px-4 py-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <p className="text-sm font-medium text-rp-text">{phaseLabel}</p>
                        <p className="mt-1 text-xs text-rp-text-muted">
                            {t('importExport.progressRecords', { processed, total, percent })}
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
                <StatCard label={t('importExport.stats.processed')} value={processed} />
                <StatCard label={t('importExport.stats.successful')} value={success} accent="success" />
                <StatCard
                    label={t('importExport.stats.failed')}
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
