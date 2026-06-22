import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const TABS = [
    { id: 'open-pos', label: 'Open POs' },
    { id: 'pending-approvals', label: 'Pending approvals' },
    { id: 'grns', label: 'GRN report' },
    { id: 'invoices', label: 'Invoices' },
    { id: 'balances', label: 'Supplier balances' },
    { id: 'exceptions', label: 'Match exceptions' },
    { id: 'returns', label: 'Purchase returns' },
];

function Reports({
    tab,
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
    const setTab = (id) => router.get(route('admin.procurement.reports'), { tab: id }, { preserveState: true });

    return (
        <>
            <Head title="Procurement reports" />
            <PageHeader title="Procurement reports" description="Open POs, payables, GRNs, and matching" />

            <div className="mb-4 flex flex-wrap gap-2">
                {TABS.map((t) => (
                    <button
                        key={t.id}
                        type="button"
                        onClick={() => setTab(t.id)}
                        className={`rounded-md px-3 py-1.5 text-sm ${
                            tab === t.id ? 'bg-teal-600 text-white' : 'border bg-card'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            <div className="overflow-hidden rounded-lg border bg-card">
                {tab === 'open-pos' && (
                    <ReportTable
                        headers={['Reference', 'Supplier', 'Status', 'Total']}
                        rows={openPos.map((o) => [
                            <Link href={route('admin.purchase-orders.show', o.id)} className="text-teal-600">
                                {o.reference_no}
                            </Link>,
                            o.supplier,
                            o.status,
                            o.total,
                        ])}
                    />
                )}
                {tab === 'pending-approvals' && (
                    <ReportTable
                        headers={['Reference', 'Supplier', 'Total', 'Submitted']}
                        rows={pendingApprovals.map((o) => [
                            <Link href={route('admin.purchase-orders.show', o.id)} className="text-teal-600">
                                {o.reference_no}
                            </Link>,
                            o.supplier,
                            o.total,
                            o.submitted_at?.slice(0, 10),
                        ])}
                    />
                )}
                {tab === 'grns' && (
                    <ReportTable
                        headers={['Reference', 'Supplier', 'PO', 'Received']}
                        rows={grns.map((g) => [g.reference_no, g.supplier, g.po, g.received_at?.slice(0, 10)])}
                    />
                )}
                {tab === 'invoices' && (
                    <ReportTable
                        headers={['Reference', 'Supplier', 'Status', 'Total', 'Match', '']}
                        rows={invoices.map((i) => [
                            i.reference_no,
                            i.supplier,
                            i.status,
                            i.total,
                            i.match_status ?? '—',
                            can('procurement.view') ? (
                                <a
                                    href={route('admin.supplier-invoices.pdf', i.id)}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-teal-600 text-sm hover:underline"
                                >
                                    <FileText className="h-3.5 w-3.5" />
                                    {t('pages.purchaseOrders.actions.downloadInvoicePdf')}
                                </a>
                            ) : null,
                        ])}
                    />
                )}
                {tab === 'balances' && (
                    <ReportTable
                        headers={['Code', 'Supplier', 'Balance', 'On-time %']}
                        rows={supplierBalances.map((s) => [
                            s.code,
                            <Link href={route('admin.suppliers.show', s.id)} className="text-teal-600">
                                {s.name}
                            </Link>,
                            s.balance,
                            s.on_time_delivery_rate ?? '—',
                        ])}
                    />
                )}
                {tab === 'exceptions' && (
                    <ReportTable
                        headers={['Invoice', 'PO', 'Status', 'Reason', '']}
                        rows={matchExceptions.map((m) => [
                            m.invoice,
                            m.po,
                            m.match_status,
                            m.exception_reason,
                            m.match_status === 'partially_matched' || m.match_status === 'unmatched' ? (
                                <button
                                    type="button"
                                    className="text-teal-600 text-sm"
                                    onClick={() => router.post(route('admin.po-match-results.resolve', m.id))}
                                >
                                    Resolve
                                </button>
                            ) : null,
                        ])}
                    />
                )}
                {tab === 'returns' && (
                    <ReportTable
                        headers={['Reference', 'Supplier', 'Status']}
                        rows={returns.map((r) => [r.reference_no, r.supplier, r.status])}
                    />
                )}
            </div>
        </>
    );
}

function ReportTable({ headers, rows }) {
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
                            No records.
                        </td>
                    </tr>
                )}
            </tbody>
        </table>
    );
}

export default withAdminLayout(Reports);
