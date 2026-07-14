import { CircleDashed, Plus, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export function CartTabs({
    carts,
    activeCartId,
    onSelect,
    onNew,
    onRemove,
    maxReached,
    processing,
}) {
    return (
        <div className="flex shrink-0 items-center gap-2 overflow-x-auto border-b border-[var(--pos-border)] bg-[var(--pos-bg)] px-4 py-2.5 sm:px-5">
            {carts.map((cart) => {
                const isActive = cart.id === activeCartId;
                const itemCount = cart.items?.length ?? 0;
                const isSuspended = cart.status === 'suspended';
                const isCompleting = cart.status === 'completing';

                return (
                    <div
                        key={cart.id}
                        className={cn(
                            'group flex shrink-0 items-center gap-2 rounded-[7px] border px-3 py-1.5 text-xs font-semibold transition-colors',
                            isActive
                                ? 'border-[var(--pos-teal-500)] bg-[var(--pos-teal-50)] text-[var(--pos-teal-700)]'
                                : 'border-[var(--pos-border)] bg-[var(--pos-bg)] text-[var(--pos-text-2)] hover:border-[var(--pos-border-strong)]',
                        )}
                    >
                        {isCompleting ? (
                            <button
                                type="button"
                                onClick={(e) => onRemove?.(cart.id, e)}
                                disabled={processing}
                                title="Dismiss Cart"
                                className="text-orange-500/80 hover:text-orange-600 disabled:opacity-40"
                            >
                                <CircleDashed className="h-3.5 w-3.5" />
                            </button>
                        ) : null}

                        <button
                            type="button"
                            onClick={() => onSelect(cart.id)}
                            disabled={processing}
                            title={`Ctrl+${cart.slot}`}
                            className="flex items-center gap-2 disabled:opacity-40"
                        >
                            <span>Cart {cart.slot}</span>
                            {itemCount > 0 ? (
                                <span
                                    className={cn(
                                        'rounded-full px-1.5 py-0.5 text-[10.5px] font-bold',
                                        isActive
                                            ? 'bg-[var(--pos-teal-600)] text-white'
                                            : 'bg-[var(--pos-bg-sunken)] text-[var(--pos-text-2)]',
                                    )}
                                >
                                    {itemCount}
                                </span>
                            ) : null}
                            {isActive ? (
                                <span className="rounded bg-[var(--pos-teal-100)] px-1.5 py-0.5 text-[9.5px] font-extrabold tracking-wider text-[var(--pos-teal-700)]">
                                    ACTIVE
                                </span>
                            ) : null}
                            {isSuspended ? (
                                <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[9.5px] font-extrabold tracking-wider text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                                    HOLD
                                </span>
                            ) : null}
                            {isCompleting ? (
                                <span className="rounded bg-orange-100 px-1.5 py-0.5 text-[9.5px] font-extrabold tracking-wider text-orange-700 dark:bg-orange-500/20 dark:text-orange-300">
                                    PAYING
                                </span>
                            ) : null}
                        </button>

                        {cart.status !== 'completing' ? (
                            <button
                                type="button"
                                onClick={(e) => onRemove?.(cart.id, e)}
                                disabled={processing}
                                title="Close Cart"
                                className="text-[14px] leading-none text-[var(--pos-text-3)] hover:text-[var(--pos-danger)] disabled:opacity-40"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        ) : null}
                    </div>
                );
            })}

            <button
                type="button"
                onClick={onNew}
                disabled={maxReached || processing}
                className="flex shrink-0 items-center gap-1.5 rounded-[7px] border border-dashed border-[var(--pos-border-strong)] px-3 py-1.5 text-xs font-semibold text-[var(--pos-text-2)] transition hover:border-[var(--pos-teal-500)] hover:text-[var(--pos-teal-700)] disabled:cursor-not-allowed disabled:opacity-40"
            >
                <Plus className="h-3.5 w-3.5" />
                New Cart
            </button>
        </div>
    );
}
