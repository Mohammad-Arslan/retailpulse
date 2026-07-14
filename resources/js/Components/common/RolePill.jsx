import { rolePillVariant } from '@/lib/avatar';
import { cn } from '@/lib/utils';

const VARIANTS = {
    'super-admin':
        'bg-ink-900 text-sand-300 dark:bg-ink-800 dark:text-teal-300 dark:ring-1 dark:ring-teal-500/30',
    manager:
        'bg-violet-100 text-violet-500 dark:bg-violet-500/20 dark:text-violet-300',
    cashier: 'bg-teal-100 text-teal-500 dark:bg-teal-500/20 dark:text-teal-300',
    accountant:
        'bg-amber-100 text-amber-500 dark:bg-amber-500/20 dark:text-amber-400',
    default: 'bg-sand-100 text-ink-700 dark:bg-ink-700 dark:text-ink-300',
};

export default function RolePill({ name, displayName }) {
    const variant = rolePillVariant(name);

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold',
                VARIANTS[variant] ?? VARIANTS.default,
            )}
            title={name}
        >
            <span className="h-1.5 w-1.5 rounded-full bg-current" />
            {displayName || name}
        </span>
    );
}
