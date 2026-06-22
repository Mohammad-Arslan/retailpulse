import PageHeader from '@/Components/common/PageHeader';
import Select, { mapToSelectOptions } from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import {
    grnStatusLabel,
    invoiceStatusLabel,
    matchStatusLabel,
    poStatusLabel,
    returnStatusLabel,
} from '@/lib/procurementI18n';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const TAB_IDS = [
    'open-pos',
    'pending-approvals',
    'grns',
    'invoices',
    'balances',
    'exceptions',
    'returns',
];

function Reports({
    tab,
    filters,
    suppliers = [],
    invoiceStatuses = [],
    matchStatuses = [],
    returnStatuses = [],
    openPos,
    pendingApprovals,
    grns,
    invoices,
    supplierBalances,
    matchExceptions,
    returns,
}) {
    const can = useCan();
    const { t } = useTranslation();

    const tabLabel = (id) => {
        const keyMap = {
            'open-pos': 'openPos',
            'pending-approvals': 'pendingApprovals',
            grns: 'grns',
            invoices: 'invoices',
            balances: 'balances',
            exceptions: 'exceptions',
            returns: 'returns',
        };

        return t(`pages.procurementReports.tabs.${keyMap[id]}`);
    };

    const setTab = (id) =>
        router.get(route('admin.procurement.reports'), { ...filters, tab: id }, { preserveState: true });

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        const params = Object.fromEntries(form.entries());
        router.get(route('admin.procurement.reports'), { ...params, tab }, { preserveState: true });
    };

    const supplierOptions = useMemo(
        () => [
            { value: '', label: t('common.allSuppliers') },
            ...mapToSelectOptions(suppliers),
        ],
        [suppliers, t],
    );

    const invoiceStatusOptions = useMemo(
        () => [
            { value: '', label: t('common.allStatuses') },
            ...invoiceStatuses.map((status) => ({
                value: status,
                label: invoiceStatusLabel(t, status),
            })),
        ],
        [invoiceStatuses, t],
    );

    const matchStatusOptions = useMemo(
        () => [
            { value: '', label: t('pages.procurementReports.filters.allMatchStatuses') },
            ...matchStatuses.map((status) => ({
                value: status,
                label: matchStatusLabel(t, status),
            })),
        ],
        [matchStatuses, t],
    );

    const returnStatusOptions = useMemo(
        () => [
            { value: '', label: t('common.allStatuses') },
            ...returnStatuses.map((status) => ({
                value: status,
                label: returnStatusLabel(t, status),
            })),
        ],
        [returnStatuses, t],
    );

    const col = (key) => t(`pages.procurementReports.columns.${key}`);

    return (
        <>
            <Head title={t('pages.procurementReports.title')} />
            <PageHeader
                title={t('pages.procurementReports.title')}
                description={t('pages.procurementReports.description')}
            />

            <div className="mb-4 flex flex-wrap gap-2">
                {TAB_IDS.map((id) => (
                    <button
                        key={id}
                        type="button"
                        onClick={() => setTab(id)}
                        className={`rounded-md px-3 py-1.5 text-sm ${
                            tab === id ? 'bg-teal-600 text-white' : 'border bg-card'
                        }`}
                    >
                        {tabLabel(id)}
                    </button>
                ))}
            </div>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <input type="hidden" name="tab" value={tab} />
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.procurementReports.filters.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                {['open-pos', 'pending-approvals', 'grns', 'invoices', 'exceptions', 'returns'].includes(tab) && (
                    <Select
                        name="supplier_id"
                        defaultValue={filters.supplier_id ?? ''}
                        className="w-auto min-w-[12rem]"
                        options={supplierOptions}
                    />
                )}
                {tab === 'grns' && (
                    <>
                        <div className="flex flex-col gap-1">
                            <label className="text-xs text-muted-foreground">
                                {t('pages.procurementReports.filters.dateFrom')}
                            </label>
                            <input
                                type="date"
                                name="from"
                                defaultValue={filters.from ?? ''}
                                className="rp-form-input h-9"
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <label className="text-xs text-muted-foreground">
                                {t('pages.procurementReports.filters.dateTo')}
                            </label>
                            <input
                                type="date"
                                name="to"
                                defaultValue={filters.to ?? ''}
                                className="rp-form-input h-9"
                            />
                        </div>
                    </>
                )}
                {tab === 'invoices' && (
                    <>
                        <Select
                            name="status"
                            defaultValue={filters.status ?? ''}
                            className="w-auto min-w-[10rem]"
                            options={invoiceStatusOptions}
                        />
                        <Select
                            name="match_status"
                            defaultValue={filters.match_status ?? ''}
                            className="w-auto min-w-[12rem]"
                            options={matchStatusOptions}
                        />
                    </>
                )}
                {tab === 'returns' && (
                    <Select
                        name="status"
                        defaultValue={filters.status ?? ''}
                        className="w-auto min-w-[10rem]"
                        options={returnStatusOptions}
                    />
                )}
                <button type="submit" className="rp-btn-outline self-end">
                    {t('common.search')}
                </button>
            </form>

            <div className="overflow-hidden rounded-lg border bg-card">
                {tab === 'open-pos' && (
                    <ReportTable
                        headers={[col('reference'), col('supplier'), col('status'), col('total')]}
                        rows={openPos.map((o) => [
                            <Link href={route('admin.purchase-orders.show', o.id)} className="text-teal-600">
                                {o.reference_no}
                            </Link>,
                            o.supplier,
                            poStatusLabel(t, o.status),
                            o.total,
                        ])}
                        emptyMessage={t('pages.procurementReports.empty')}
                    />
                )}
                {tab === 'pending-approvals' && (
                    <ReportTable
                        headers={[col('reference'), col('supplier'), col('total'), col('submitted')]}
                        rows={pendingApprovals.map((o) => [
                            <Link href={route('admin.purchase-orders.show', o.id)} className="text-teal-600">
                                {o.reference_no}
                            </Link>,
                            o.supplier,
                            o.total,
                            o.submitted_at?.slice(0, 10),
                        ])}
                        emptyMessage={t('pages.procurementReports.empty')}
                    />
                )}
                {tab === 'grns' && (
                    <ReportTable
                        headers={[col('reference'), col('supplier'), col('po'), col('received'), col('status')]}
                        rows={grns.map((g) => [
                            <Link href={route('admin.goods-receiving-notes.show', g.id)} className="text-teal-600">
                                {g.reference_no}
                            </Link>,
                            g.supplier,
                            g.po,
                            g.received_at?.slice(0, 10),
                            grnStatusLabel(t, g.status),
                        ])}
                        emptyMessage={t('pages.procurementReports.empty')}
                    />
                )}
                {tab === 'invoices' && (
                    <ReportTable
                        headers={[
                            col('reference'),
                            col('supplier'),
                            col('status'),
                            col('total'),
                            col('match'),
                            col('actions'),
                        ]}
                        rows={invoices.map((i) => [
                            i.reference_no,
                            i.supplier,
                            invoiceStatusLabel(t, i.status),
                            i.total,
                            i.match_status ? matchStatusLabel(t, i.match_status) : '—',
                            can('procurement.view') ? (
                                <a
                                    href={route('admin.supplier-invoices.pdf', i.id)}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-sm text-teal-600 hover:underline"
                                >
                                    <FileText className="h-3.5 w-3.5" />
                                    {t('pages.purchaseOrders.actions.downloadInvoicePdf')}
                                </a>
                            ) : null,
                        ])}
                        emptyMessage={t('pages.procurementReports.empty')}
                    />
                )}
                {tab === 'balances' && (
                    <ReportTable
                        headers={[col('code'), col('supplier'), col('balance'), col('onTimePercent')]}
                        rows={supplierBalances.map((s) => [
                            s.code,
                            <Link href={route('admin.suppliers.show', s.id)} className="text-teal-600">
                                {s.name}
                            </Link>,
                            s.balance,
                            s.on_time_delivery_rate ?? '—',
                        ])}
                        emptyMessage={t('pages.procurementReports.empty')}
                    />
                )}
                {tab === 'exceptions' && (
                    <ReportTable
                        headers={[col('invoice'), col('po'), col('status'), col('reason'), col('actions')]}
                        rows={matchExceptions.map((m) => [
                            m.invoice,
                            m.po,
                            matchStatusLabel(t, m.match_status),
                            m.exception_reason,
                            m.match_status === 'partially_matched' || m.match_status === 'unmatched' ? (
                                <button
                                    type="button"
                                    className="text-sm text-teal-600"
                                    onClick={() => router.post(route('admin.po-match-results.resolve', m.id))}
                                >
                                    {t('pages.procurementReports.resolve')}
                                </button>
                            ) : null,
                        ])}
                        emptyMessage={t('pages.procurementReports.empty')}
                    />
                )}
                {tab === 'returns' && (
                    <ReportTable
                        headers={[col('reference'), col('supplier'), col('status')]}
                        rows={returns.map((r) => [
                            r.reference_no,
                            r.supplier,
                            returnStatusLabel(t, r.status),
                        ])}
                        emptyMessage={t('pages.procurementReports.empty')}
                    />
                )}
            </div>
        </>
    );
}

function ReportTable({ headers, rows, emptyMessage }) {
    return (
        <table className="w-full text-left text-sm">
            <thead className="border-b bg-muted/40 text-muted-foreground">
                <tr>
                    {headers.map((h) => (
                        <th key={h} className="px-4 py-3">
                            {h}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {rows?.length ? (
                    rows.map((row, i) => (
                        <tr key={i} className="border-b">
                            {row.map((cell, j) => (
                                <td key={j} className="px-4 py-3">
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))
                ) : (
                    <tr>
                        <td colSpan={headers.length} className="px-4 py-8 text-center text-muted-foreground">
                            {emptyMessage}
                        </td>
                    </tr>
                )}
            </tbody>
        </table>
    );
}

export default withAdminLayout(Reports);
