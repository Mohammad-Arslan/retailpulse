import { cn } from '@/lib/utils';

export default function BrandIcon({ className, iconClassName }) {
    return (
        <div
            className={cn(
                'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-teal-500',
                className,
            )}
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                className={cn('h-6 w-6 text-white', iconClassName)}
            >
                <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
                <line x1="3" y1="6" x2="21" y2="6" />
                <path d="M16 10a4 4 0 01-8 0" />
            </svg>
        </div>
    );
}
