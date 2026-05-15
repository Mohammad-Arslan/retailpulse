import { rolePillVariant } from '@/lib/avatar';
import { cn } from '@/lib/utils';

const VARIANTS = {
    'super-admin': 'bg-ink-900 text-sand-300',
    manager: 'bg-violet-100 text-violet-500',
    cashier: 'bg-teal-100 text-teal-500',
    accountant: 'bg-amber-100 text-amber-500',
    default: 'bg-sand-100 text-ink-700',
};

export default function RolePill({ name }) {
    const variant = rolePillVariant(name);

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold',
                VARIANTS[variant] ?? VARIANTS.default,
            )}
        >
            <span className="h-1.5 w-1.5 rounded-full bg-current" />
            {name}
        </span>
    );
}
