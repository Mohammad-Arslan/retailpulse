import { Package } from 'lucide-react';
import { formatPkr } from '@/lib/posCartTotals';
import { cn } from '@/lib/utils';

function ProductCard({ product, onAdd, disabled }) {
    return (
        <button
            type="button"
            onClick={() => onAdd(product)}
            disabled={disabled || !product.in_stock}
            className={cn(
                'flex flex-col overflow-hidden rounded-xl border border-rp-border bg-rp-surface text-left transition-all hover:border-blue-500/40 hover:shadow-lg hover:shadow-blue-500/5 disabled:cursor-not-allowed disabled:opacity-50',
            )}
        >
            <div className="flex aspect-square items-center justify-center bg-rp-surface-inset">
                {product.image_url ? (
                    <img
                        src={product.image_url}
                        alt={product.name}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <Package className="h-12 w-12 text-rp-text-muted opacity-30" />
                )}
            </div>
            <div className="flex flex-1 flex-col gap-1 p-3">
                <p className="line-clamp-2 text-sm font-semibold text-rp-text">{product.name}</p>
                <p className="truncate text-[11px] text-rp-text-muted">{product.sku}</p>
                <div className="mt-auto flex items-end justify-between gap-2 pt-2">
                    <span className="text-sm font-bold text-rp-text">
                        PKR {formatPkr(product.unit_price)}
                    </span>
                    {product.in_stock ? (
                        <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-medium text-emerald-400">
                            In Stock {product.available_stock}
                        </span>
                    ) : (
                        <span className="rounded-full bg-red-500/15 px-2 py-0.5 text-[10px] font-medium text-red-400">
                            Out of stock
                        </span>
                    )}
                </div>
            </div>
        </button>
    );
}

export function ProductGrid({ products, loading, meta, perPage, onPageChange, onPerPageChange, onAddProduct, processing }) {
    if (loading && products.length === 0) {
        return (
            <div className="flex flex-1 items-center justify-center text-rp-text-muted">
                <div className="h-8 w-8 animate-spin rounded-full border-2 border-blue-500 border-t-transparent" />
            </div>
        );
    }

    if (!loading && products.length === 0) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center text-rp-text-muted">
                <Package className="h-16 w-16 opacity-20" />
                <p className="mt-4 text-sm">No products found</p>
                <p className="text-xs">Try a different search or category</p>
            </div>
        );
    }

    const pages = meta?.last_page ?? 1;
    const current = meta?.current_page ?? 1;

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="grid flex-1 auto-rows-min grid-cols-2 gap-3 overflow-y-auto p-4 xl:grid-cols-3 2xl:grid-cols-4">
                {products.map((product) => (
                    <ProductCard
                        key={product.id}
                        product={product}
                        onAdd={onAddProduct}
                        disabled={processing}
                    />
                ))}
            </div>

            <div className="flex shrink-0 items-center justify-between border-t border-rp-border px-4 py-3">
                <div className="flex items-center gap-1">
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
                                    'flex h-8 min-w-8 items-center justify-center rounded-lg text-xs font-medium transition-colors',
                                    page === current
                                        ? 'bg-blue-600 text-white'
                                        : 'text-rp-text-secondary hover:bg-rp-surface-subtle',
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
                    className="rounded-lg border border-rp-border bg-rp-surface-subtle px-2 py-1.5 text-xs text-rp-text-secondary outline-none"
                >
                    {[12, 24, 36, 48].map((n) => (
                        <option key={n} value={n}>
                            {n} per page
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
}
