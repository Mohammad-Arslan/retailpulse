import { ChevronDown, ChevronUp, Eye } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function ImportDataPreview({ headers = [], rows = [], filename }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    if (!headers.length) {
        return null;
    }

    return (
        <div className="rounded-lg border border-rp-border bg-rp-surface-subtle">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
            >
                <span className="flex items-center gap-2 text-sm font-medium text-rp-text">
                    <Eye className="h-4 w-4 text-rp-text-muted" />
                    {t('importExport.previewData')}
                    {filename ? (
                        <span className="font-normal text-rp-text-muted">({filename})</span>
                    ) : null}
                </span>
                {open ? (
                    <ChevronUp className="h-4 w-4 text-rp-text-muted" />
                ) : (
                    <ChevronDown className="h-4 w-4 text-rp-text-muted" />
                )}
            </button>
            {open && (
                <div className="overflow-x-auto border-t border-rp-border">
                    <table className="min-w-full text-xs text-rp-text">
                        <thead className="bg-rp-surface-inset">
                            <tr>
                                {headers.map((header) => (
                                    <th
                                        key={header}
                                        className="px-3 py-2 text-left font-medium text-rp-text-secondary"
                                    >
                                        {header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.slice(0, 5).map((row, index) => (
                                <tr key={index} className="border-t border-rp-border-subtle">
                                    {headers.map((header) => (
                                        <td key={header} className="px-3 py-2 text-rp-text-secondary">
                                            {String(row[header] ?? '')}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {rows.length > 5 && (
                        <p className="border-t border-rp-border px-3 py-2 text-xs text-rp-text-muted">
                            {t('importExport.previewMoreRows', { count: rows.length - 5 })}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
