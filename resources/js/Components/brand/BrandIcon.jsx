import { cn } from '@/lib/utils';

export default function BrandIcon({ className, iconClassName }) {
    return (
        <div
            className={cn(
                'flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-linear-to-br from-teal-300 to-teal-500',
                'shadow-[0_2px_10px_rgba(42,124,111,0.35),inset_0_1px_0_rgba(255,255,255,0.2)]',
                className,
            )}
        >
            <svg
                viewBox="0 0 20 20"
                fill="none"
                className={cn('h-4.5 w-4.5', iconClassName)}
            >
                <rect x="2" y="12" width="4" height="7" rx="1.25" fill="rgba(255,255,255,0.4)" />
                <rect x="8" y="7.5" width="4" height="11.5" rx="1.25" fill="rgba(255,255,255,0.65)" />
                <rect x="14" y="3" width="4" height="16" rx="1.25" fill="white" />
                <circle cx="16" cy="2.5" r="1.5" fill="rgba(255,255,255,0.9)" />
            </svg>
        </div>
    );
}
