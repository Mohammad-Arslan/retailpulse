import { cn } from '@/lib/utils';

export default function FormInfoPanel({ icon: Icon, title, children, className = '' }) {
    return (
        <div className={cn('rp-card h-fit space-y-3 bg-rp-surface-inset/50', className)}>
            <div className="flex items-center gap-2.5">
                {Icon && (
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                        <Icon className="h-4 w-4" />
                    </span>
                )}
                <h3 className="rp-section-title">{title}</h3>
            </div>
            <div className="space-y-2 text-sm text-rp-text-secondary [&_ol]:list-decimal [&_ol]:space-y-1.5 [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:space-y-1.5 [&_ul]:pl-5">
                {children}
            </div>
        </div>
    );
}
