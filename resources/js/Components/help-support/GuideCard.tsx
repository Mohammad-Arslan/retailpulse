import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    title: string;
    description: string;
    icon: LucideIcon;
    href?: string;
    status?: 'available' | 'comingSoon';
    badge?: ReactNode;
};

export default function GuideCard({
    title,
    description,
    icon: Icon,
    href,
    status = 'available',
    badge,
}: Props) {
    const disabled = status !== 'available' || !href;

    const content = (
        <div
            className={cn(
                'rp-card group flex flex-col gap-3 p-5 transition',
                disabled
                    ? 'cursor-not-allowed opacity-70'
                    : 'cursor-pointer hover:-translate-y-0.5 hover:border-teal-400/40 hover:shadow-md',
            )}
            aria-disabled={disabled}
        >
            <div className="flex items-start justify-between gap-3">
                <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-500/10 text-teal-600 dark:text-teal-400">
                    <Icon className="h-5 w-5" />
                </span>
                <div className="flex items-center gap-2">
                    {badge}
                    <ChevronRight
                        className={cn(
                            'h-5 w-5 text-ink-400 transition',
                            disabled
                                ? 'opacity-50'
                                : 'group-hover:translate-x-0.5 group-hover:text-teal-500',
                        )}
                    />
                </div>
            </div>
            <div>
                <h3 className="font-semibold text-ink-900 dark:text-white">{title}</h3>
                <p className="mt-1 text-sm text-ink-500 dark:text-ink-300">{description}</p>
            </div>
        </div>
    );

    if (disabled) return content;

    return (
        <Link href={href} className="block">
            {content}
        </Link>
    );
}

