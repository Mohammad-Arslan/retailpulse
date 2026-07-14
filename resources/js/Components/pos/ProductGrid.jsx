import ScrollArea from '@/Components/common/ScrollArea';
import { formatPkr } from '@/lib/posCartTotals';
import { cn } from '@/lib/utils';
import { Package } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function StockBadge({ product }) {
    const { t } = useTranslation();

    if (
        product.tracks_inventory === false ||
        product.product_type === 'service' ||
        product.product_type === 'digital'
    ) {
        return (
            <span className="rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold text-sky-700 dark:bg-sky-500/20 dark:text-sky-300">
                {product.product_type === 'digital'
                    ? t('pages.pos.catalog.digital')
                    : t('pages.pos.catalog.service')}
            </span>
        );
    }

    if (!product.in_stock) {
        return (
            <span className="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-[var(--pos-danger)] dark:bg-rose-500/20">
                {t('pages.pos.catalog.outOfStock')}
            </span>
        );
    }

    const low =
        product.available_stock != null && Number(product.available_stock) > 0 && Number(product.available_stock) <= 10;

    return (
        <span
            className={cn(
                'rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                low
                    ? 'bg-[#fdf1e0] text-[var(--pos-warn)]'
                    : 'bg-[var(--pos-success-bg)] text-[var(--pos-success)]',
            )}
        >
            {product.available_stock != null
                ? t('pages.pos.catalog.inStockCount', { count: product.available_stock })
                : t('pages.pos.catalog.inStock')}
        </span>
    );
}

function ProductCard({ product, onAdd, disabled, currency }) {
    return (
        <button
            type="button"
            onClick={() => onAdd(product)}
            disabled={
                disabled ||
                (!(
                    product.tracks_inventory === false ||
                    product.product_type === 'service' ||
                    product.product_type === 'digital'
                ) &&
                    !product.in_stock)
            }
            className={cn(
                'group flex min-w-0 flex-col gap-2 rounded-[9px] border border-[var(--pos-border)] bg-[var(--pos-bg)] p-3 text-left transition-all duration-100',
                'hover:-translate-y-px hover:border-[var(--pos-teal-500)] hover:shadow-[var(--pos-shadow-sm)]',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--pos-teal-500)]',
                'disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:translate-y-0 disabled:hover:border-[var(--pos-border)] disabled:hover:shadow-none',
            )}
        >
            <div className="flex h-16 items-center justify-center rounded-md bg-[var(--pos-bg-sunken)]">
                {product.image_url ? (
                    <img
                        src={product.image_url}
                        alt=""
                        className="h-full w-full rounded-md object-cover"
                    />
                ) : (
                    <Package className="h-5 w-5 text-[var(--pos-text-3)]" />
                )}
            </div>
            <div className="min-w-0">
                <p className="line-clamp-2 min-h-[34px] text-[12.5px] font-semibold leading-snug text-[var(--pos-text-1)]">
                    {product.name}
                </p>
                <p className="pos-mono mt-0.5 truncate text-[10.5px] text-[var(--pos-text-3)]">
                    {product.sku}
                </p>
            </div>
            <div className="mt-auto flex items-center justify-between gap-2">
                <span className="pos-mono truncate text-[13px] font-bold text-[var(--pos-text-1)]">
                    {currency} {formatPkr(product.unit_price)}
                </span>
                <StockBadge product={product} />
            </div>
        </button>
    );
}

export function ProductGrid({
    products,
    loading,
    meta,
    perPage,
    onPageChange,
    onPerPageChange,
    onAddProduct,
    processing,
    currency = 'PKR',
}) {
    const { t } = useTranslation();

    if (loading && products.length === 0) {
        return (
            <div className="flex flex-1 items-center justify-center text-[var(--pos-text-3)]">
                <div className="h-8 w-8 animate-spin rounded-full border-2 border-[var(--pos-teal-500)] border-t-transparent" />
            </div>
        );
    }

    if (!loading && products.length === 0) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center px-6 text-center text-[var(--pos-text-3)]">
                <Package className="h-14 w-14 opacity-30" />
                <p className="mt-3 text-sm font-medium text-[var(--pos-text-1)]">
                    {t('pages.pos.catalog.emptyTitle')}
                </p>
                <p className="mt-1 text-xs">{t('pages.pos.catalog.emptyHint')}</p>
            </div>
        );
    }

    const pages = meta?.last_page ?? 1;
    const current = meta?.current_page ?? 1;
    const total = meta?.total;

    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
            <div className="flex shrink-0 items-center justify-between px-4 pb-3 text-[11.5px] font-semibold text-[var(--pos-text-2)] sm:px-5">
                <span>
                    {typeof total === 'number'
                        ? t('pages.pos.catalog.resultCount', { count: total })
                        : null}
                </span>
                <span>{t('pages.pos.catalog.sortedByName')}</span>
            </div>

            <ScrollArea className="pos-scroll min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-4 pb-4 sm:px-5">
                <div className="grid grid-cols-[repeat(auto-fill,minmax(168px,1fr))] gap-3">
                    {products.map((product) => (
                        <ProductCard
                            key={product.id}
                            product={product}
                            onAdd={onAddProduct}
                            disabled={processing}
                            currency={currency}
                        />
                    ))}
                </div>
            </ScrollArea>

            <div className="flex shrink-0 items-center justify-between gap-2 border-t border-[var(--pos-border)] bg-[var(--pos-bg)] px-4 py-3 sm:px-5">
                <div className="flex min-w-0 flex-wrap items-center gap-1.5">
                    {Array.from({ length: Math.min(pages, 7) }, (_, i) => {
                        let page = i + 1;
                        if (pages > 7) {
                            if (current <= 4) page = i + 1;
                            else if (current >= pages - 3) page = pages - 6 + i;
                            else page = current - 3 + i;
                        }

                        return (
                            <button
                                key={page}
                                type="button"
                                onClick={() => onPageChange(page)}
                                className={cn(
                                    'flex h-[26px] w-[26px] items-center justify-center rounded-md border text-xs font-bold transition-colors',
                                    page === current
                                        ? 'border-[var(--pos-teal-600)] bg-[var(--pos-teal-600)] text-white'
                                        : 'border-[var(--pos-border)] bg-[var(--pos-bg)] text-[var(--pos-text-2)] hover:border-[var(--pos-border-strong)]',
                                )}
                            >
                                {page}
                            </button>
                        );
                    })}
                </div>

                <select
                    value={perPage}
                    onChange={(e) => onPerPageChange(Number(e.target.value))}
                    className="shrink-0 rounded-[7px] border border-[var(--pos-border)] bg-[var(--pos-bg)] px-2.5 py-1.5 text-xs font-semibold text-[var(--pos-text-2)] outline-none"
                    aria-label={t('pages.pos.catalog.perPage')}
                >
                    {[12, 24, 36, 48].map((n) => (
                        <option key={n} value={n}>
                            {t('pages.pos.catalog.perPageOption', { count: n })}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
}
