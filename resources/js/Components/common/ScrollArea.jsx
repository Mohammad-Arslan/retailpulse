import { forwardRef } from 'react';
import { cn } from '@/lib/utils';

/**
 * Scrollable region with the RetailPulse-themed scrollbar (matches the admin sidebar).
 */
const ScrollArea = forwardRef(function ScrollArea(
    { as: Component = 'div', className, children, ...props },
    ref,
) {
    return (
        <Component ref={ref} className={cn('rp-scroll', className)} {...props}>
            {children}
        </Component>
    );
});

export default ScrollArea;
