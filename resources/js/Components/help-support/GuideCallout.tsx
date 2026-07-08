import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

type Props = {
    tone?: 'info' | 'warn' | 'danger';
    children: ReactNode;
};

export default function GuideCallout({ tone = 'info', children }: Props) {
    const cls =
        tone === 'warn'
            ? 'border-l-[color:var(--g-amber)] bg-[color:var(--g-amber-wash)]'
            : tone === 'danger'
                ? 'border-l-[color:var(--g-red)] bg-[color:var(--g-red-wash)]'
                : 'border-l-[color:var(--g-teal-dim)] bg-[color:var(--g-teal-wash)]';

    return (
        <div
            className={cn(
                'rounded-lg border border-[color:var(--g-border)] border-l-[3px] px-4 py-3 text-[13.3px] text-[color:var(--g-text-dim)]',
                cls,
            )}
        >
            {children}
        </div>
    );
}

