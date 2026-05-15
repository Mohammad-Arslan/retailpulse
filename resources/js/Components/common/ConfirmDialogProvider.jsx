import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { cn } from '@/lib/utils';
import { AlertTriangle, Trash2 } from 'lucide-react';
import { createContext, useCallback, useContext, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

const ConfirmDialogContext = createContext(null);

const defaultOptions = {
    title: '',
    description: '',
    confirmLabel: '',
    cancelLabel: '',
    variant: 'default',
};

export function ConfirmDialogProvider({ children }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [options, setOptions] = useState(defaultOptions);
    const resolveRef = useRef(null);

    const confirm = useCallback((input) => {
        const resolved =
            typeof input === 'string'
                ? { description: input, variant: 'destructive' }
                : input;

        return new Promise((resolve) => {
            resolveRef.current = resolve;
            setOptions({
                title: resolved.title ?? t('confirm.defaultTitle'),
                description: resolved.description ?? '',
                confirmLabel: resolved.confirmLabel ?? t('confirm.confirm'),
                cancelLabel: resolved.cancelLabel ?? t('confirm.cancel'),
                variant: resolved.variant ?? 'destructive',
            });
            setOpen(true);
        });
    }, [t]);

    const settle = useCallback((result) => {
        setOpen(false);
        resolveRef.current?.(result);
        resolveRef.current = null;
    }, []);

    const isDestructive = options.variant === 'destructive';
    const Icon = isDestructive ? Trash2 : AlertTriangle;

    return (
        <ConfirmDialogContext.Provider value={{ confirm }}>
            {children}

            <AlertDialog
                open={open}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        settle(false);
                    }
                }}
            >
                <AlertDialogContent className="gap-5">
                    <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                        <span
                            className={cn(
                                'flex h-12 w-12 shrink-0 items-center justify-center rounded-full',
                                isDestructive
                                    ? 'bg-rose-100 text-rose-500 dark:bg-rose-500/20 dark:text-rose-300'
                                    : 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300',
                            )}
                        >
                            <Icon className="h-5 w-5" aria-hidden />
                        </span>

                        <AlertDialogHeader className="flex-1 sm:text-left">
                            <AlertDialogTitle>{options.title}</AlertDialogTitle>
                            {options.description && (
                                <AlertDialogDescription>
                                    {options.description}
                                </AlertDialogDescription>
                            )}
                        </AlertDialogHeader>
                    </div>

                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => settle(false)}>
                            {options.cancelLabel}
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => settle(true)}
                            className={cn(
                                isDestructive &&
                                    'bg-rose-500 text-white hover:bg-rose-600 focus-visible:ring-rose-500/30',
                            )}
                        >
                            {options.confirmLabel}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </ConfirmDialogContext.Provider>
    );
}

export function useConfirm() {
    const context = useContext(ConfirmDialogContext);

    if (!context) {
        throw new Error('useConfirm must be used within ConfirmDialogProvider');
    }

    return context.confirm;
}
