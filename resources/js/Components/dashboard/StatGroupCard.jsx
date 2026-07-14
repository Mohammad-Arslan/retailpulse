import { Link } from '@inertiajs/react';

const ICON_VARIANTS = {
    blue: 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-300',
    violet: 'bg-violet-100 text-violet-600 dark:bg-violet-500/20 dark:text-violet-300',
    teal: 'bg-teal-100 text-teal-500 dark:bg-teal-500/20 dark:text-teal-300',
};

const TONE_CLASS = {
    warn: 'text-amber-600 dark:text-amber-400',
    danger: 'text-rose-600 dark:text-rose-400',
};

/**
 * Compact domain card: colored header icon + label/value rows + optional footer link.
 */
export default function StatGroupCard({
    title,
    icon: Icon,
    iconVariant = 'teal',
    rows = [],
    footerHref,
    footerLabel,
}) {
    return (
        <div className="rp-card flex h-full flex-col !p-[22px]">
            <div className="mb-4 flex items-center gap-2.5">
                <div
                    className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-[9px] ${
                        ICON_VARIANTS[iconVariant] ?? ICON_VARIANTS.teal
                    }`}
                >
                    {Icon ? <Icon className="h-[15px] w-[15px]" /> : null}
                </div>
                <h3 className="text-[15px] font-semibold text-rp-text">{title}</h3>
            </div>

            <div className="flex-1">
                {rows.map((row) => (
                    <div
                        key={row.label}
                        className="flex items-baseline justify-between gap-3 border-b border-rp-border-subtle py-2.5 last:border-b-0 last:pb-0"
                    >
                        <span className="text-[12.5px] text-rp-text-secondary">{row.label}</span>
                        <span
                            className={`text-sm font-semibold tabular-nums ${
                                TONE_CLASS[row.tone] ?? 'text-rp-text'
                            }`}
                        >
                            {row.value}
                        </span>
                    </div>
                ))}
            </div>

            {footerHref && footerLabel ? (
                <Link
                    href={footerHref}
                    className="mt-3.5 block border-t border-rp-border-subtle pt-3 text-center text-xs font-semibold text-teal-600 hover:underline dark:text-teal-400"
                >
                    {footerLabel}
                </Link>
            ) : null}
        </div>
    );
}
