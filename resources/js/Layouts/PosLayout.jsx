import { cn } from '@/lib/utils';

/**
 * Dedicated POS application shell — no admin sidebar / command palette.
 */
export default function PosLayout({ children, className }) {
    return (
        <div
            className={cn(
                'pos-shell flex h-dvh max-h-dvh min-h-0 min-w-0 flex-col overflow-hidden font-sans',
                className,
            )}
        >
            {children}
        </div>
    );
}
