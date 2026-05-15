import { ADMIN_NAV_SECTIONS } from '@/config/adminNav';
import { useCan } from '@/Hooks/useCan';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

export default function CommandPalette({ open, onClose }) {
    const can = useCan();
    const [query, setQuery] = useState('');
    const inputRef = useRef(null);

    const items = useMemo(() => {
        return ADMIN_NAV_SECTIONS.flatMap((section) =>
            section.items
                .filter((item) => can(item.permission))
                .map((item) => ({
                    ...item,
                    section: section.label,
                })),
        );
    }, [can]);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        if (!q) {
            return items;
        }

        return items.filter(
            (item) =>
                item.label.toLowerCase().includes(q) ||
                item.section.toLowerCase().includes(q) ||
                item.keywords?.some((keyword) => keyword.includes(q)),
        );
    }, [items, query]);

    useEffect(() => {
        if (open) {
            setQuery('');
            requestAnimationFrame(() => inputRef.current?.focus());
        }
    }, [open]);

    const navigate = (href) => {
        onClose();
        router.visit(route(href));
    };

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-[100] flex items-start justify-center px-4 pt-[12vh]">
            <button
                type="button"
                className="absolute inset-0 bg-ink-900/60 backdrop-blur-sm"
                onClick={onClose}
                aria-label="Close search"
            />
            <div className="relative z-10 w-full max-w-lg overflow-hidden rounded-2xl border border-rp-border bg-rp-surface shadow-2xl">
                <div className="flex items-center gap-3 border-b border-rp-border px-4 py-3">
                    <Search className="h-4 w-4 shrink-0 text-rp-text-muted" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search pages, users, actions..."
                        className="w-full border-0 bg-transparent text-sm text-rp-text outline-none placeholder:text-rp-text-muted"
                    />
                    <kbd className="hidden rounded border border-rp-border bg-rp-surface-inset px-1.5 py-0.5 text-[10px] text-rp-text-muted sm:inline">
                        ESC
                    </kbd>
                </div>
                <ul className="max-h-72 overflow-y-auto p-2">
                    {filtered.length === 0 ? (
                        <li className="px-3 py-6 text-center text-sm text-rp-text-muted">
                            No results found.
                        </li>
                    ) : (
                        filtered.map((item) => {
                            const Icon = item.icon;

                            return (
                                <li key={item.href}>
                                    <button
                                        type="button"
                                        onClick={() => navigate(item.href)}
                                        className={cn(
                                            'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition',
                                            'hover:bg-rp-surface-inset',
                                        )}
                                    >
                                        <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-teal-500 dark:bg-teal-500/20 dark:text-teal-300">
                                            <Icon className="h-4 w-4" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-medium text-rp-text">
                                                {item.label}
                                            </span>
                                            <span className="block text-xs text-rp-text-muted">
                                                {item.section}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            );
                        })
                    )}
                </ul>
            </div>
        </div>
    );
}
