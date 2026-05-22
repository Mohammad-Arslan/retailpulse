import { Button } from '@/Components/ui/button';
import { useCan } from '@/Hooks/useCan';
import { cn } from '@/lib/utils';
import { Loader2, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function BulkSelectionBar({ selectedCount = 0, onClear, actions = [] }) {
    const can = useCan();
    const { t } = useTranslation();

    if (selectedCount <= 0) {
        return null;
    }

    const visibleActions = actions.filter(
        (action) => !action.permission || can(action.permission),
    );

    return (
        <div
            className="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center px-4"
            role="region"
            aria-live="polite"
            aria-label={t('bulk.selectionBar')}
        >
            <div className="pointer-events-auto flex max-w-full flex-wrap items-center gap-2 rounded-xl border border-ink-200 bg-white px-3 py-2.5 shadow-2xl dark:border-ink-700 dark:bg-ink-900">
                <span className="px-2 text-sm font-medium text-rp-text">
                    {t('bulk.selectedCount', { count: selectedCount })}
                </span>

                <div className="hidden h-5 w-px bg-ink-200 sm:block dark:bg-ink-700" />

                {visibleActions.map((action) => {
                    const Icon = action.icon;
                    const isDestructive = action.variant === 'destructive';

                    return (
                        <Button
                            key={action.id}
                            type="button"
                            size="sm"
                            variant={isDestructive ? 'destructive' : 'outline'}
                            disabled={action.disabled || action.loading}
                            onClick={action.onClick}
                            className={cn(
                                'gap-1.5',
                                !isDestructive &&
                                    'border-ink-200 bg-transparent hover:bg-teal-500/10 hover:text-teal-600 dark:border-ink-700 dark:hover:text-teal-300',
                            )}
                        >
                            {action.loading ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                Icon && <Icon className="h-4 w-4" />
                            )}
                            {action.label}
                        </Button>
                    );
                })}

                <Button
                    type="button"
                    size="icon-sm"
                    variant="ghost"
                    onClick={onClear}
                    className="ml-1 text-rp-text-muted hover:text-rp-text"
                    aria-label={t('bulk.clearSelection')}
                >
                    <X className="h-4 w-4" />
                </Button>
            </div>
        </div>
    );
}
