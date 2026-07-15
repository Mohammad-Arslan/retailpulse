import ImportLogsDialog from '@/Components/import-export/ImportLogsDialog';
import ImportWizardDialog from '@/Components/import-export/ImportWizardDialog';
import { useCan } from '@/Hooks/useCan';
import { initiateExport, templateDownloadUrl } from '@/lib/importExportApi';
import { Download, FileDown, FileText, Upload } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

const ENTITY_PERMISSIONS = {
    categories: { import: 'products.import', export: 'products.export' },
    brands: { import: 'products.import', export: 'products.export' },
    units: { import: 'products.import', export: 'products.export' },
    products: { import: 'products.import', export: 'products.export' },
    customers: { import: 'customers.import', export: 'customers.export' },
    employees: { import: 'employees.import', export: 'employees.export' },
    suppliers: { import: 'suppliers.import', export: 'suppliers.export' },
    'supplier-price-lists': {
        import: 'supplier-price-lists.import',
        export: 'supplier-price-lists.export',
    },
    inventory: { import: 'inventory.import-opening-stock', export: 'inventory.reports' },
    'inventory-adjustments': {
        import: 'inventory.bulk-adjustment-import',
        export: 'inventory.reports',
    },
};

export default function ImportExportToolbar({
    entityType,
    entityLabel,
    exportOptions = {},
    showMatchField = false,
    showImport = true,
    showExport = true,
    onJobStarted,
}) {
    const can = useCan();
    const { t } = useTranslation();
    const [wizardOpen, setWizardOpen] = useState(false);
    const [logsOpen, setLogsOpen] = useState(false);
    const [exporting, setExporting] = useState(false);
    const [recentImportUlid, setRecentImportUlid] = useState(null);

    const permissions = ENTITY_PERMISSIONS[entityType] ?? ENTITY_PERMISSIONS.products;
    const canImport = showImport && can(permissions.import);
    const canExport = showExport && can(permissions.export);

    if (!canImport && !canExport) {
        return null;
    }

    const handleExport = async () => {
        setExporting(true);

        try {
            const result = await initiateExport(entityType, exportOptions);
            onJobStarted?.(result);
            toast.success(t('importExport.exportStarted'));
        } catch (error) {
            toast.error(error?.response?.data?.message ?? t('importExport.exportFailed'));
        } finally {
            setExporting(false);
        }
    };

    const handleImportStarted = (payload) => {
        if (payload?.ulid) {
            setRecentImportUlid(payload.ulid);
        }

        onJobStarted?.(payload);
    };

    return (
        <>
            <div className="flex flex-wrap items-center gap-2">
                {canImport && (
                    <>
                        <a
                            href={templateDownloadUrl(entityType)}
                            className="rp-btn-outline"
                        >
                            <FileDown className="h-4 w-4" />
                            {t('importExport.template')}
                        </a>
                        <button
                            type="button"
                            className="rp-btn-outline"
                            onClick={() => setWizardOpen(true)}
                        >
                            <Upload className="h-4 w-4" />
                            {t('importExport.import')}
                        </button>
                        <button
                            type="button"
                            className="rp-btn-outline"
                            onClick={() => setLogsOpen(true)}
                        >
                            <FileText className="h-4 w-4" />
                            {t('importExport.logs')}
                        </button>
                    </>
                )}
                {canExport && (
                    <button
                        type="button"
                        className="rp-btn-outline"
                        disabled={exporting}
                        onClick={handleExport}
                    >
                        <Download className="h-4 w-4" />
                        {exporting ? t('importExport.exporting') : t('importExport.export')}
                    </button>
                )}
            </div>
            {canImport && (
                <>
                    <ImportWizardDialog
                        open={wizardOpen}
                        onClose={() => setWizardOpen(false)}
                        entityType={entityType}
                        entityLabel={entityLabel}
                        showMatchField={showMatchField}
                        onJobStarted={handleImportStarted}
                    />
                    <ImportLogsDialog
                        open={logsOpen}
                        onClose={() => setLogsOpen(false)}
                        entityType={entityType}
                        entityLabel={entityLabel}
                        ulid={recentImportUlid}
                    />
                </>
            )}
        </>
    );
}

export function openExportDownload(ulid, downloadUrl = null) {
    const url =
        downloadUrl && typeof downloadUrl === 'string'
            ? downloadUrl
            : route('admin.import-export.jobs.download', ulid, false);

    window.open(url, '_blank', 'noopener,noreferrer');
}
