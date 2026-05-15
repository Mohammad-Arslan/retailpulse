import { cn } from '@/lib/utils';

export default function FormCard({ children, className = '' }) {
    return (
        <div className={cn('rp-card max-w-2xl space-y-5', className)}>
            {children}
        </div>
    );
}
