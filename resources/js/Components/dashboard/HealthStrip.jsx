import { useTranslation } from 'react-i18next';

const SEVERITY_RANK = { critical: 0, warning: 1, info: 2 };

function highestSeverity(items) {
    let best = null;
    for (const item of items) {
        const rank = SEVERITY_RANK[item.severity] ?? 9;
        if (best === null || rank < best.rank) {
            best = { severity: item.severity, rank };
        }
    }
    return best?.severity ?? null;
}

function Dot({ severity }) {
    if (severity === 'critical') {
        return (
            <span
                className="h-2.5 w-2.5 shrink-0 rounded-full bg-rose-500 shadow-[0_0_0_4px] shadow-rose-100 dark:shadow-rose-500/20"
                aria-hidden
            />
        );
    }
    if (severity === 'warning') {
        return (
            <span
                className="h-2.5 w-2.5 shrink-0 rounded-full bg-amber-500 shadow-[0_0_0_4px] shadow-amber-100 dark:shadow-amber-500/20"
                aria-hidden
            />
        );
    }
    return (
        <span
            className="h-2.5 w-2.5 shrink-0 rounded-full bg-teal-500 shadow-[0_0_0_4px] shadow-teal-100 dark:shadow-teal-500/20"
            aria-hidden
        />
    );
}

/**
 * Compact summary of open business exceptions. Sources from business_exceptions widget data.
 */
export default function HealthStrip({ items = [] }) {
    const { t } = useTranslation();
    const count = items.length;
    const topSeverity = count > 0 ? highestSeverity(items) : null;
    const stripSeverity =
        topSeverity === 'critical' ? 'critical' : topSeverity != null ? 'warning' : null;

    return (
        <div className="mb-7 flex flex-wrap items-center gap-3.5 rounded-2xl border border-rp-border bg-rp-surface px-5 py-3.5">
            <Dot severity={stripSeverity} />
            <p className="min-w-0 flex-1 text-[13.5px] text-rp-text">
                {count === 0 ? (
                    t('pages.dashboard.health.allClear')
                ) : (
                    <>
                        <span className="font-semibold">
                            {t('pages.dashboard.health.itemCount', { count })}
                        </span>{' '}
                        {t('pages.dashboard.health.needAttentionSuffix')}
                        {items[0]?.title ? (
                            <>
                                {' '}
                                — {items.slice(0, 2).map((i) => i.title).join(', ')}
                                {count > 2
                                    ? t('pages.dashboard.health.moreSuffix', {
                                          count: count - 2,
                                      })
                                    : null}
                            </>
                        ) : null}
                    </>
                )}
            </p>
            {count > 0 ? (
                <a
                    href="#dashboard-attention"
                    className="shrink-0 text-[12.5px] font-semibold text-teal-600 hover:underline dark:text-teal-400"
                >
                    {t('pages.dashboard.health.reviewAll')}
                </a>
            ) : null}
        </div>
    );
}
