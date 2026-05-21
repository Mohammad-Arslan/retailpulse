import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useCan } from '@/Hooks/useCan';
import { buildCatalogExportOptions } from '@/lib/catalogExportOptions';
import { initiateExport } from '@/lib/importExportApi';
import { router } from '@inertiajs/react';
import { Ban, Download, Trash2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

const ENTITY_PERMISSIONS = {
    products: {
        export: 'products.export',
        update: 'products.update',
        delete: 'products.delete',
    },
    categories: {
        export: 'products.export',
        update: 'products.update',
        delete: 'products.delete',
    },
    brands: {
        export: 'products.export',
        update: 'products.update',
        delete: 'products.delete',
    },
    units: {
        export: 'products.export',
        update: 'products.update',
        delete: 'products.delete',
    },
};

export function useCatalogBulkActions({
    entityType,
    selectedArray,
    onClear,
    onJobStarted,
    exportOptions = {},
}) {
    const can = useCan();
    const confirm = useConfirm();
    const { t } = useTranslation();
    const [exporting, setExporting] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [deactivating, setDeactivating] = useState(false);

    const permissions = ENTITY_PERMISSIONS[entityType] ?? ENTITY_PERMISSIONS.products;
    const count = selectedArray.length;

    const handleExport = useCallback(async () => {
        if (count === 0) {
            return;
        }

        setExporting(true);

        try {
            const result = await initiateExport(
                entityType,
                buildCatalogExportOptions(exportOptions, selectedArray),
            );

            onJobStarted?.(result);
            toast.success(t('importExport.exportStarted'));
            onClear?.();
        } catch (error) {
            toast.error(error?.response?.data?.message ?? t('importExport.exportFailed'));
        } finally {
            setExporting(false);
        }
    }, [count, entityType, exportOptions, onClear, onJobStarted, selectedArray, t]);

    const handleDelete = useCallback(async () => {
        if (count === 0) {
            return;
        }

        const confirmed = await confirm({
            title: t('confirm.deleteTitle'),
            description: t('bulk.deleteConfirm', { count }),
            confirmLabel: t('common.delete'),
            cancelLabel: t('confirm.cancel'),
            variant: 'destructive',
        });

        if (!confirmed) {
            return;
        }

        setDeleting(true);

        router.post(
            route('admin.catalog.bulk.delete'),
            { entity: entityType, ids: selectedArray },
            {
                preserveScroll: true,
                onSuccess: () => onClear?.(),
                onFinish: () => setDeleting(false),
            },
        );
    }, [confirm, count, entityType, onClear, selectedArray, t]);

    const handleDeactivate = useCallback(async () => {
        if (count === 0) {
            return;
        }

        const confirmed = await confirm({
            title: t('bulk.deactivateTitle'),
            description: t('bulk.deactivateConfirm', { count }),
            confirmLabel: t('common.deactivate'),
            cancelLabel: t('confirm.cancel'),
        });

        if (!confirmed) {
            return;
        }

        setDeactivating(true);

        router.post(
            route('admin.catalog.bulk.deactivate'),
            { entity: entityType, ids: selectedArray },
            {
                preserveScroll: true,
                onSuccess: () => onClear?.(),
                onFinish: () => setDeactivating(false),
            },
        );
    }, [confirm, count, entityType, onClear, selectedArray, t]);

    return useMemo(() => {
        const actions = [];

        if (can(permissions.export)) {
            actions.push({
                id: 'export',
                label: t('bulk.exportSelected'),
                icon: Download,
                permission: permissions.export,
                onClick: () => void handleExport(),
                loading: exporting,
                disabled: exporting,
            });
        }

        if (can(permissions.update)) {
            actions.push({
                id: 'deactivate',
                label: t('bulk.deactivateSelected'),
                icon: Ban,
                permission: permissions.update,
                onClick: () => void handleDeactivate(),
                loading: deactivating,
                disabled: deactivating,
            });
        }

        if (can(permissions.delete)) {
            actions.push({
                id: 'delete',
                label: t('bulk.deleteSelected'),
                icon: Trash2,
                permission: permissions.delete,
                variant: 'destructive',
                onClick: () => void handleDelete(),
                loading: deleting,
                disabled: deleting,
            });
        }

        return actions;
    }, [
        can,
        deactivating,
        deleting,
        exporting,
        handleDeactivate,
        handleDelete,
        handleExport,
        permissions,
        t,
    ]);
}
