import { cn } from '@/lib/utils';
import { Bell } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export default function NotificationsMenu() {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        const handleClick = (event) => {
            if (ref.current && !ref.current.contains(event.target)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClick);

        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className={cn(
                    'relative flex h-9 w-9 items-center justify-center rounded-lg border border-sand-200 bg-white transition',
                    'hover:border-teal-400 dark:border-ink-700 dark:bg-ink-800 dark:hover:border-teal-400',
                )}
                title="Notifications"
            >
                <Bell className="h-4 w-4 text-ink-700 dark:text-sand-300" />
                <span className="absolute top-1.5 right-1.5 h-2 w-2 rounded-full border border-white bg-rose-500 dark:border-ink-800" />
            </button>

            {open && (
                <div className="absolute top-full right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-sand-200 bg-white shadow-lg dark:border-ink-700 dark:bg-ink-800">
                    <div className="border-b border-sand-200 px-4 py-3 dark:border-ink-700">
                        <p className="text-sm font-semibold text-ink-900 dark:text-white">
                            Notifications
                        </p>
                    </div>
                    <div className="px-4 py-8 text-center">
                        <p className="text-sm text-ink-500">You&apos;re all caught up.</p>
                        <p className="mt-1 text-xs text-ink-300">
                            New alerts will appear here.
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}
