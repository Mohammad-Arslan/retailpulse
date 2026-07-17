import { cn } from '@/lib/utils';
import { X } from 'lucide-react';

export default function ModalHeader({ icon: Icon, title, description, onClose, className = '' }) {
    return (
        <div className={cn('flex items-start justify-between gap-4 border-b border-rp-border px-6 py-4', className)}>
            <div className="flex items-center gap-3">
                {Icon && (
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                        <Icon className="h-4 w-4" />
                    </span>
                )}
                <div>
                    <h3 className="text-base font-semibold text-rp-text">{title}</h3>
                    {description && <p className="text-xs text-rp-text-muted">{description}</p>}
                </div>
            </div>
            {onClose && (
                <button
                    type="button"
                    onClick={onClose}
                    className="shrink-0 rounded-lg p-1.5 text-rp-text-muted transition hover:bg-rp-surface-inset hover:text-rp-text"
                    aria-label="Close"
                >
                    <X className="h-4 w-4" />
                </button>
            )}
        </div>
    );
}
