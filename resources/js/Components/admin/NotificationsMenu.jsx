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
                    'relative flex h-9 w-9 items-center justify-center rounded-lg border border-rp-border bg-rp-surface transition',
                    'hover:border-teal-400',
                )}
                title="Notifications"
            >
                <Bell className="h-4 w-4 text-rp-text-secondary" />
                <span className="absolute top-1.5 right-1.5 h-2 w-2 rounded-full border border-rp-surface bg-rose-500" />
            </button>

            {open && (
                <div className="absolute top-full right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-rp-border bg-rp-surface shadow-lg">
                    <div className="border-b border-rp-border px-4 py-3">
                        <p className="text-sm font-semibold text-rp-text">
                            Notifications
                        </p>
                    </div>
                    <div className="px-4 py-8 text-center">
                        <p className="text-sm text-rp-text-secondary">
                            You&apos;re all caught up.
                        </p>
                        <p className="mt-1 text-xs text-rp-text-muted">
                            New alerts will appear here.
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}
