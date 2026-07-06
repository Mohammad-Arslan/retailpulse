import { cn } from '@/lib/utils';
import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function PaginationLinkContent({ label }) {
    const normalized = label
        .replace(/&laquo;\s*Previous/i, 'Previous')
        .replace(/Next\s*&raquo;/i, 'Next')
        .trim();

    if (/^previous$/i.test(normalized)) {
        return (
            <>
                <ChevronLeft className="h-4 w-4 shrink-0" aria-hidden />
                <span className="hidden sm:inline">Previous</span>
            </>
        );
    }

    if (/^next$/i.test(normalized)) {
        return (
            <>
                <span className="hidden sm:inline">Next</span>
                <ChevronRight className="h-4 w-4 shrink-0" aria-hidden />
            </>
        );
    }

    return <span dangerouslySetInnerHTML={{ __html: label }} />;
}

export default function ListPaginationBar({
    pagination,
    filters = {},
    indexRoute,
    pageSizeOptions = [10, 15, 25, 50, 100],
}) {
    const { t } = useTranslation();

    if (!pagination) {
        return null;
    }

    const currentPerPage = Number(
        filters.per_page ?? pagination.per_page ?? pageSizeOptions[1] ?? 15,
    );

    const handlePerPageChange = (event) => {
        if (!indexRoute) {
            return;
        }

        router.get(
            route(indexRoute),
            { ...filters, per_page: event.target.value, page: 1 },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <div className="border-t border-rp-border bg-rp-surface-inset px-4 py-3.5">
            <div className="flex flex-col gap-3">
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <span className="whitespace-nowrap text-[13px] text-rp-text-secondary">
                        {t('common.showing', {
                            from: pagination.from ?? 0,
                            to: pagination.to ?? 0,
                            total: pagination.total ?? 0,
                        })}
                    </span>
                    {indexRoute && (
                        <label className="flex shrink-0 items-center gap-2 whitespace-nowrap text-[13px] text-rp-text-secondary">
                            <span>{t('common.rowsPerPage')}</span>
                            <select
                                value={String(currentPerPage)}
                                onChange={handlePerPageChange}
                                aria-label={t('common.rowsPerPage')}
                                className="h-8 rounded-[7px] border border-rp-border bg-rp-surface px-2 text-[13px] font-medium text-rp-text focus-visible:ring-2 focus-visible:ring-teal-400 focus-visible:outline-none"
                            >
                                {pageSizeOptions.map((size) => (
                                    <option key={size} value={size}>
                                        {size}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                </div>
                {pagination.last_page > 1 && (
                    <div className="rp-table-pagination w-full">
                        <div className="flex flex-nowrap items-center justify-center gap-1 sm:justify-end">
                            {pagination.links?.map((link, i) => {
                                const isPrevious = /previous/i.test(link.label);
                                const isNext = /next/i.test(link.label);
                                const baseClass =
                                    'inline-flex h-8 shrink-0 items-center justify-center gap-1 whitespace-nowrap rounded-[7px] border text-[13px] font-medium transition focus-visible:ring-2 focus-visible:ring-teal-400 focus-visible:outline-none';
                                const sizeClass =
                                    isPrevious || isNext ? 'px-2.5' : 'min-w-8 px-2.5';
                                const stateClass = link.active
                                    ? 'border-ink-900 bg-ink-900 text-white dark:border-teal-500 dark:bg-teal-500'
                                    : link.url
                                      ? 'border-rp-border bg-rp-surface text-rp-text hover:border-ink-900 hover:bg-ink-900 hover:text-white dark:hover:border-teal-500 dark:hover:bg-teal-500'
                                      : 'cursor-default border-rp-border bg-rp-surface text-rp-text-muted';
                                const linkClass = cn(baseClass, sizeClass, stateClass);

                                if (link.url) {
                                    return (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            preserveState
                                            preserveScroll
                                            className={linkClass}
                                            aria-label={
                                                isPrevious
                                                    ? t('common.previousPage')
                                                    : isNext
                                                      ? t('common.nextPage')
                                                      : undefined
                                            }
                                        >
                                            <PaginationLinkContent label={link.label} />
                                        </Link>
                                    );
                                }

                                return (
                                    <span
                                        key={i}
                                        className={linkClass}
                                        aria-current={link.active ? 'page' : undefined}
                                    >
                                        <PaginationLinkContent label={link.label} />
                                    </span>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
