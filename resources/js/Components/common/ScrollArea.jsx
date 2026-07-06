import { cn } from '@/lib/utils';

/**
 * Scrollable region with the RetailPulse-themed scrollbar (matches the admin sidebar).
 */
export default function ScrollArea({ as: Component = 'div', className, children, ...props }) {
    return (
        <Component className={cn('rp-scroll', className)} {...props}>
            {children}
        </Component>
    );
}
