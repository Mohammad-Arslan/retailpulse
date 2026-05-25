import { cn } from '@/lib/utils';

export function CategoryFilters({ categories, activeId, onSelect }) {
    const items = [{ id: null, name: 'All Products' }, ...categories];

    return (
        <div className="flex shrink-0 gap-2 overflow-x-auto border-b border-rp-border bg-rp-surface-subtle/50 px-4 py-3">
            {items.map((cat) => {
                const active = activeId === cat.id || (cat.id === null && activeId === null);

                return (
                    <button
                        key={cat.id ?? 'all'}
                        type="button"
                        onClick={() => onSelect(cat.id)}
                        className={cn(
                            'shrink-0 rounded-full px-4 py-1.5 text-xs font-medium transition-colors',
                            active
                                ? 'bg-blue-600 text-white'
                                : 'border border-rp-border bg-rp-surface text-rp-text-secondary hover:border-rp-text-muted hover:text-rp-text',
                        )}
                    >
                        {cat.name}
                    </button>
                );
            })}
        </div>
    );
}
