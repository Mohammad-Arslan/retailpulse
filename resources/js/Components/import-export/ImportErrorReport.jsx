import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { fetchJobRowErrors } from '@/lib/importExportApi';
import { AlertTriangle, ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function ImportErrorReport({ ulid }) {
    const { t } = useTranslation();
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [page, setPage] = useState(1);
    const [data, setData] = useState(null);

    useEffect(() => {
        const timer = setTimeout(() => setDebouncedSearch(search), 300);

        return () => clearTimeout(timer);
    }, [search]);

    const loadErrors = useCallback(async () => {
        if (!ulid) {
            return;
        }

        setLoading(true);

        try {
            const response = await fetchJobRowErrors(ulid, {
                search: debouncedSearch,
                page,
            });
            setData(response);
        } catch {
            setData(null);
        } finally {
            setLoading(false);
        }
    }, [ulid, debouncedSearch, page]);

    useEffect(() => {
        loadErrors();
    }, [loadErrors]);

    useEffect(() => {
        setPage(1);
    }, [debouncedSearch]);

    const summary = data?.summary;
    const pagination = data?.pagination;
    const rows = data?.rows ?? [];

    return (
        <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="rounded-lg border border-rp-border px-4 py-3">
                    <p className="text-xs text-rp-text-muted">{t('importExport.errorReport.totalErrors')}</p>
                    <p className="text-lg font-semibold text-rp-text">
                        {summary?.total_errors ?? 0}
                    </p>
                </div>
                <div className="rounded-lg border border-rp-border px-4 py-3">
                    <p className="text-xs text-rp-text-muted">{t('importExport.errorReport.affectedRows')}</p>
                    <p className="text-lg font-semibold text-rp-text">
                        {summary?.affected_rows ?? 0}
                    </p>
                </div>
                <div className="rounded-lg border border-rp-border px-4 py-3">
                    <p className="text-xs text-rp-text-muted">{t('importExport.errorReport.commonErrors')}</p>
                    <p className="text-lg font-semibold text-rp-text">
                        {summary?.top_errors?.length ?? 0}
                    </p>
                </div>
            </div>

            {summary?.top_errors?.length > 0 && (
                <div className="rounded-lg border border-amber-500/20 bg-amber-500/5 px-4 py-3">
                    <div className="flex items-start gap-2">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div className="min-w-0">
                            <p className="text-sm font-medium text-rp-text">
                                {t('importExport.errorReport.topErrorsTitle')}
                            </p>
                            <ul className="mt-2 space-y-1 text-xs text-rp-text-secondary">
                                {summary.top_errors.map((entry) => (
                                    <li key={entry.message}>
                                        <span className="font-medium">{entry.count}×</span> {entry.message}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            )}

            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-rp-text-muted" />
                <input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder={t('importExport.errorReport.searchPlaceholder')}
                    className="rp-form-input w-full pl-9"
                />
            </div>

            <div className="overflow-hidden rounded-lg border border-rp-border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('importExport.errorReport.row')}</TableHead>
                            <TableHead>{t('importExport.errorReport.column')}</TableHead>
                            <TableHead>{t('importExport.errorReport.value')}</TableHead>
                            <TableHead>{t('importExport.errorReport.message')}</TableHead>
                            <TableHead>{t('importExport.errorReport.severity')}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {loading ? (
                            <TableRow>
                                <TableCell colSpan={5} className="py-8 text-center text-rp-text-muted">
                                    {t('importExport.errorReport.loading')}
                                </TableCell>
                            </TableRow>
                        ) : rows.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5} className="py-8 text-center text-rp-text-muted">
                                    {t('importExport.errorReport.empty')}
                                </TableCell>
                            </TableRow>
                        ) : (
                            rows.map((row, index) => (
                                <TableRow key={`${row.row_index}-${row.column}-${index}`}>
                                    <TableCell>{row.row_index}</TableCell>
                                    <TableCell>{row.column}</TableCell>
                                    <TableCell className="max-w-[160px] truncate" title={row.value}>
                                        {row.value}
                                    </TableCell>
                                    <TableCell className="max-w-[240px]">{row.message}</TableCell>
                                    <TableCell className="capitalize">{row.severity}</TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            {pagination && pagination.last_page > 1 && (
                <div className="flex items-center justify-between text-sm">
                    <p className="text-rp-text-muted">
                        {t('importExport.errorReport.pageInfo', {
                            page: pagination.current_page,
                            total: pagination.last_page,
                        })}
                    </p>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            className="rp-btn-outline inline-flex items-center gap-1"
                            disabled={pagination.current_page <= 1}
                            onClick={() => setPage((value) => Math.max(1, value - 1))}
                        >
                            <ChevronLeft className="h-4 w-4" />
                            {t('importExport.back')}
                        </button>
                        <button
                            type="button"
                            className="rp-btn-outline inline-flex items-center gap-1"
                            disabled={pagination.current_page >= pagination.last_page}
                            onClick={() => setPage((value) => value + 1)}
                        >
                            {t('common.continue')}
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
