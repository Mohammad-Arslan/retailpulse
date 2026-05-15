import { cn } from '@/lib/utils';
import { Search } from 'lucide-react';

export default function SidebarSearch({ collapsed, onOpen }) {
    if (collapsed) {
        return (
            <div className="px-2 pb-2">
                <button
                    type="button"
                    onClick={onOpen}
                    className="flex h-10 w-full items-center justify-center rounded-lg bg-ink-800 text-ink-300 transition hover:bg-ink-700 hover:text-white"
                    title="Search (⌘K)"
                >
                    <Search className="h-4 w-4" />
                </button>
            </div>
        );
    }

    return (
        <div className="px-4 pb-3">
            <button
                type="button"
                onClick={onOpen}
                className={cn(
                    'flex w-full items-center gap-2 rounded-lg bg-ink-800 px-3 py-2 text-left transition',
                    'hover:bg-ink-700 focus-visible:ring-2 focus-visible:ring-teal-400/50 focus-visible:outline-none',
                )}
            >
                <Search className="h-3.5 w-3.5 shrink-0 text-ink-300" />
                <span className="flex-1 text-xs text-ink-300">Search...</span>
                <kbd className="rounded border border-ink-700 bg-ink-900 px-1.5 py-0.5 text-[10px] text-ink-300">
                    ⌘K
                </kbd>
            </button>
        </div>
    );
}
