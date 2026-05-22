import { cn } from '@/lib/utils';
import { Search } from 'lucide-react';

export default function SidebarSearch({ collapsed, onOpen }) {
    if (collapsed) {
        return (
            <div className="px-2 pb-2">
                <button
                    type="button"
                    onClick={onOpen}
                    className="flex h-10 w-full items-center justify-center rounded-lg border border-rp-border bg-rp-surface-inset text-rp-text-muted transition hover:bg-rp-surface-subtle hover:text-rp-text dark:border-transparent dark:bg-ink-800 dark:text-ink-300 dark:hover:bg-ink-700 dark:hover:text-white"
                    title="Search (⌘K)"
                >
                    <Search className="h-4 w-4" />
                </button>
            </div>
        );
    }

    return (
        <div className="px-3 pt-3 pb-2">
            <button
                type="button"
                onClick={onOpen}
                className={cn(
                    'flex w-full items-center gap-2.5 rounded-lg border border-rp-border bg-rp-surface-inset px-3 py-2.5 text-left transition',
                    'hover:border-teal-400/40 hover:bg-rp-surface-subtle focus-visible:ring-2 focus-visible:ring-teal-400/50 focus-visible:outline-none',
                    'dark:border-ink-700 dark:bg-ink-800 dark:hover:border-teal-500/30 dark:hover:bg-ink-700',
                )}
            >
                <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted dark:text-ink-400" />
                <span className="flex-1 text-[12.5px] text-rp-text-muted dark:text-ink-400">Search...</span>
                <kbd className="rounded-md border border-rp-border bg-rp-surface px-1.5 py-0.5 text-[10px] font-medium text-rp-text-muted dark:border-ink-600 dark:bg-ink-900 dark:text-ink-400">
                    ⌘K
                </kbd>
            </button>
        </div>
    );
}
