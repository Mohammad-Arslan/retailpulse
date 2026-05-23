import ImportLogsDialog from '@/Components/import-export/ImportLogsDialog';
import ImportWizardDialog from '@/Components/import-export/ImportWizardDialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { useCan } from '@/Hooks/useCan';
import { initiateExport, templateDownloadUrl } from '@/lib/importExportApi';
import { Link } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ChevronDown,
    Download,
    SlidersHorizontal,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

const IMPORT_TYPES = {
    opening: {
        entityType: 'inventory',
        permission: 'inventory.import-opening-stock',
    },
    adjustments: {
        entityType: 'inventory-adjustments',
        permission: 'inventory.bulk-adjustment-import',
    },
};

export default function InventoryPageActions({ exportOptions = {}, onJobStarted }) {
    const can = useCan();
    const { t } = useTranslation();
    const [activeImport, setActiveImport] = useState(null);
    const [logsOpen, setLogsOpen] = useState(false);
    const [logsEntityType, setLogsEntityType] = useState('inventory');
    const [recentImportUlid, setRecentImportUlid] = useState(null);
    const [exporting, setExporting] = useState(false);

    const canOpening = can(IMPORT_TYPES.opening.permission);
    const canAdjustments = can(IMPORT_TYPES.adjustments.permission);
    const canImport = canOpening || canAdjustments;
    const canExport = can('inventory.reports');
    const canTransfer = can('inventory.transfer');
    const canReceive = can('inventory.receive');
    const canAdjust = can('inventory.adjust');

    const hasUtilities = canImport || canExport;
    const hasOperations = canTransfer || canReceive || canAdjust;

    if (!hasUtilities && !hasOperations) {
        return null;
    }

    const handleImportStarted = (type, payload) => {
        const entityType = IMPORT_TYPES[type].entityType;

        if (payload?.ulid) {
            setRecentImportUlid(payload.ulid);
        }

        setLogsEntityType(entityType);
        onJobStarted?.(payload);
    };

    const handleExport = async () => {
        setExporting(true);

        try {
            const result = await initiateExport('inventory', exportOptions);
            onJobStarted?.(result);
            toast.success(t('importExport.exportStarted'));
        } catch (error) {
            toast.error(error?.response?.data?.message ?? t('importExport.exportFailed'));
        } finally {
            setExporting(false);
        }
    };

    const logsLabel =
        logsEntityType === 'inventory-adjustments'
            ? t('pages.inventory.bulkAdjustmentsImport')
            : t('pages.inventory.openingStockImport');

    return (
        <>
            <div className="flex w-full min-w-0 flex-nowrap items-center justify-between gap-3">
                {hasUtilities ? (
                    <div className="flex shrink-0 items-center gap-2">
                        {canExport && (
                            <button
                                type="button"
                                className="rp-btn-outline hidden sm:inline-flex"
                                disabled={exporting}
                                onClick={handleExport}
                            >
                                {exporting ? t('importExport.exporting') : t('importExport.export')}
                            </button>
                        )}

                        {canImport && (
                            <DropdownMenu>
                                <DropdownMenuTrigger className="rp-btn-outline">
                                    {t('importExport.import')}
                                    <ChevronDown className="h-3.5 w-3.5 opacity-60" />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" className="min-w-[14rem]">
                                    {(canOpening || canAdjustments) && (
                                        <>
                                            <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                                                {t('importExport.template')}
                                            </DropdownMenuLabel>
                                            {canOpening && (
                                                <DropdownMenuItem asChild>
                                                    <a href={templateDownloadUrl('inventory')}>
                                                        {t('pages.inventory.openingStockImport')}
                                                    </a>
                                                </DropdownMenuItem>
                                            )}
                                            {canAdjustments && (
                                                <DropdownMenuItem asChild>
                                                    <a
                                                        href={templateDownloadUrl(
                                                            'inventory-adjustments',
                                                        )}
                                                    >
                                                        {t('pages.inventory.bulkAdjustmentsImport')}
                                                    </a>
                                                </DropdownMenuItem>
                                            )}
                                            <DropdownMenuSeparator />
                                        </>
                                    )}

                                    {canOpening && (
                                        <DropdownMenuItem onSelect={() => setActiveImport('opening')}>
                                            {t('pages.inventory.openingStockImport')}
                                        </DropdownMenuItem>
                                    )}
                                    {canAdjustments && (
                                        <DropdownMenuItem
                                            onSelect={() => setActiveImport('adjustments')}
                                        >
                                            {t('pages.inventory.bulkAdjustmentsImport')}
                                        </DropdownMenuItem>
                                    )}

                                    <DropdownMenuSeparator />

                                    <DropdownMenuItem onSelect={() => setLogsOpen(true)}>
                                        {t('importExport.logs')}
                                    </DropdownMenuItem>

                                    {canExport && (
                                        <DropdownMenuItem
                                            className="sm:hidden"
                                            disabled={exporting}
                                            onSelect={handleExport}
                                        >
                                            <Download className="h-4 w-4" />
                                            {exporting
                                                ? t('importExport.exporting')
                                                : t('importExport.export')}
                                        </DropdownMenuItem>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}

                        {!canImport && canExport && (
                            <button
                                type="button"
                                className="rp-btn-outline sm:hidden"
                                disabled={exporting}
                                onClick={handleExport}
                            >
                                {t('importExport.export')}
                            </button>
                        )}
                    </div>
                ) : (
                    <span aria-hidden="true" />
                )}

                {hasOperations && (
                    <div className="flex shrink-0 flex-nowrap items-center gap-2">
                        {canTransfer && (
                            <Link
                                href={route('admin.stock-transfers.index')}
                                className="rp-btn-outline hidden sm:inline-flex"
                            >
                                <Truck className="h-4 w-4 shrink-0 opacity-70" />
                                {t('pages.inventory.transfers')}
                            </Link>
                        )}
                        {canReceive && (
                            <Link
                                href={route('admin.inventory.receive')}
                                className="rp-btn-outline"
                            >
                                <ArrowDownToLine className="h-4 w-4 shrink-0 opacity-70 sm:hidden" />
                                <span className="hidden sm:inline">
                                    {t('pages.inventory.receive')}
                                </span>
                                <span className="sm:hidden">{t('pages.inventory.receiveShort')}</span>
                            </Link>
                        )}
                        {canAdjust && (
                            <Link
                                href={route('admin.inventory.adjust')}
                                className="rp-btn-primary rp-btn-emphasis"
                            >
                                <SlidersHorizontal className="h-4 w-4 shrink-0" />
                                <span className="hidden sm:inline">{t('pages.inventory.adjust')}</span>
                                <span className="sm:hidden">{t('pages.inventory.adjustShort')}</span>
                            </Link>
                        )}
                    </div>
                )}
            </div>

            {canOpening && (
                <ImportWizardDialog
                    open={activeImport === 'opening'}
                    onClose={() => setActiveImport(null)}
                    entityType="inventory"
                    entityLabel={t('pages.inventory.openingStockImport')}
                    onJobStarted={(payload) => handleImportStarted('opening', payload)}
                />
            )}
            {canAdjustments && (
                <ImportWizardDialog
                    open={activeImport === 'adjustments'}
                    onClose={() => setActiveImport(null)}
                    entityType="inventory-adjustments"
                    entityLabel={t('pages.inventory.bulkAdjustmentsImport')}
                    onJobStarted={(payload) => handleImportStarted('adjustments', payload)}
                />
            )}
            {canImport && (
                <ImportLogsDialog
                    open={logsOpen}
                    onClose={() => setLogsOpen(false)}
                    entityType={logsEntityType}
                    entityLabel={logsLabel}
                    ulid={recentImportUlid}
                />
            )}
        </>
    );
}
