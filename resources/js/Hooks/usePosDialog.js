import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useCallback } from 'react';
import { toast } from 'sonner';

export function usePosDialog() {
    const confirm = useConfirm();

    const error = useCallback((message) => {
        toast.error(message);
    }, []);

    const warning = useCallback((message) => {
        toast.warning(message);
    }, []);

    const success = useCallback((message, description) => {
        toast.success(message, description ? { description } : undefined);
    }, []);

    const confirmRemoveItem = useCallback(
        (name) =>
            confirm({
                title: 'Remove item',
                description: `Remove "${name}" from this cart?`,
                confirmLabel: 'Remove',
                cancelLabel: 'Cancel',
                variant: 'destructive',
            }),
        [confirm],
    );

    const confirmVoidCart = useCallback(
        () =>
            confirm({
                title: 'Void cart',
                description: 'Void this cart? This cannot be undone.',
                confirmLabel: 'Void cart',
                cancelLabel: 'Cancel',
                variant: 'destructive',
            }),
        [confirm],
    );

    const confirmCloseCart = useCallback(
        () =>
            confirm({
                title: 'Close cart',
                description: 'Close this cart? All items will be voided.',
                confirmLabel: 'Close cart',
                cancelLabel: 'Cancel',
                variant: 'destructive',
            }),
        [confirm],
    );

    return {
        confirm,
        error,
        warning,
        success,
        confirmRemoveItem,
        confirmVoidCart,
        confirmCloseCart,
    };
}
