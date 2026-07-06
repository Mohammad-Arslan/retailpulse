import PageHeader from '@/Components/common/PageHeader';
import SupplierAttachmentSection from '@/Components/admin/SupplierAttachmentSection';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, FileText, Mail, Pencil, Plus, Tag, Truck } from 'lucide-react';
import { paymentMethodLabel, supplierLedgerEntryTypeLabel } from '@/lib/procurementI18n';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Show({ supplier, ledgerEntries = [], branchId, paymentMethods = [], attachments = [], performanceScores = [] }) {
    const can = useCan();
    const { t } = useTranslation();
    const [paymentAmount, setPaymentAmount] = useState('');
    const [paymentMethod, setPaymentMethod] = useState(paymentMethods[0] ?? 'cash');
    const [paymentNotes, setPaymentNotes] = useState('');

    const supplierAttachments = attachments.length ? attachments : supplier.attachments ?? [];
    const scoreHistory = performanceScores.length ? performanceScores : supplier.performanceScores ?? [];

    const paymentMethodOptions = useMemo(
        () =>
            paymentMethods.map((method) => ({
                value: method,
                label: paymentMethodLabel(t, method),
            })),
        [paymentMethods, t],
    );

    const deactivate = () => {
        if (confirm(t('pages.suppliers.actions.deactivateConfirm', { name: supplier.name }))) {
            router.post(route('admin.suppliers.deactivate', supplier.id));
        }
    };

    const sendStatement = () => router.post(route('admin.suppliers.send-statement', supplier.id));

    const recordPayment = (e) => {
        e.preventDefault();
        if (!branchId || !paymentAmount) return;
        router.post(route('admin.supplier-payments.store'), {
            branch_id: branchId,
            supplier_id: supplier.id,
            amount: Number(paymentAmount),
            payment_method: paymentMethod,
            currency_code: supplier.currency_code ?? 'USD',
            exchange_rate: 1,
            payment_date: new Date().toISOString().slice(0, 10),
            notes: paymentNotes || null,
            is_advance: false,
        });
    };

    return (
        <>
            <Head title={supplier.name} />
            <PageHeader
                title={supplier.name}
                description={`${supplier.code} · ${supplier.is_active ? t('common.active') : t('common.inactive')}`}
            >
                <div className="flex flex-wrap gap-2">
                    {can('procurement.manage-suppliers') && (
                        <Link href={route('admin.suppliers.edit', supplier.id)} className="rp-btn-outline">
                            <Pencil className="h-4 w-4" />
                            {t('common.edit')}
                        </Link>
                    )}
                    {can('procurement.create') && (
                        <Link
                            href={route('admin.purchase-orders.create', { supplier_id: supplier.id })}
                            className="rp-btn-primary"
                        >
                            <Plus className="h-4 w-4" />
                            {t('pages.suppliers.actions.newPurchaseOrder')}
                        </Link>
                    )}
                </div>
            </PageHeader>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {can('procurement.view') && (
                    <Link
                        href={route('admin.purchase-orders.index', { supplier_id: supplier.id })}
                        className="rp-quick-action"
                    >
                        <div className="flex h-9 w-9 items-center justify-center rounded-[9px] bg-teal-100 text-teal-600 dark:bg-teal-500/20">
                            <ClipboardList className="h-4 w-4" />
                        </div>
                        <div>
                            <div className="rp-quick-action-title">{t('pages.suppliers.quickActions.purchaseOrdersTitle')}</div>
                            <div className="rp-quick-action-desc">{t('pages.suppliers.quickActions.purchaseOrdersDesc')}</div>
                        </div>
                    </Link>
                )}
                {can('procurement.manage-suppliers') && (
                    <Link
                        href={route('admin.supplier-price-lists.index', { supplier_id: supplier.id })}
                        className="rp-quick-action"
                    >
                        <div className="flex h-9 w-9 items-center justify-center rounded-[9px] bg-amber-100 text-amber-600 dark:bg-amber-500/20">
                            <Tag className="h-4 w-4" />
                        </div>
                        <div>
                            <div className="rp-quick-action-title">{t('pages.suppliers.quickActions.priceListsTitle')}</div>
                            <div className="rp-quick-action-desc">{t('pages.suppliers.quickActions.priceListsDesc')}</div>
                        </div>
                    </Link>
                )}
                <a
                    href={route('admin.suppliers.statement.pdf', supplier.id)}
                    target="_blank"
                    rel="noreferrer"
                    className="rp-quick-action"
                >
                    <div className="flex h-9 w-9 items-center justify-center rounded-[9px] bg-sky-100 text-sky-600 dark:bg-sky-500/20">
                        <FileText className="h-4 w-4" />
                    </div>
                    <div>
                        <div className="rp-quick-action-title">{t('pages.suppliers.quickActions.statementPdfTitle')}</div>
                        <div className="rp-quick-action-desc">{t('pages.suppliers.quickActions.statementPdfDesc')}</div>
                    </div>
                </a>
                {can('procurement.manage-suppliers') && supplier.email && (
                    <button type="button" onClick={sendStatement} className="rp-quick-action text-left">
                        <div className="flex h-9 w-9 items-center justify-center rounded-[9px] bg-violet-100 text-violet-600 dark:bg-violet-500/20">
                            <Mail className="h-4 w-4" />
                        </div>
                        <div>
                            <div className="rp-quick-action-title">{t('pages.suppliers.quickActions.emailStatementTitle')}</div>
                            <div className="rp-quick-action-desc">{supplier.email}</div>
                        </div>
                    </button>
                )}
                {can('procurement.manage-suppliers') && supplier.is_active && (
                    <button type="button" onClick={deactivate} className="rp-quick-action text-left">
                        <div className="flex h-9 w-9 items-center justify-center rounded-[9px] bg-rose-100 text-rose-600 dark:bg-rose-500/20">
                            <Truck className="h-4 w-4" />
                        </div>
                        <div>
                            <div className="rp-quick-action-title">{t('pages.suppliers.quickActions.deactivateTitle')}</div>
                            <div className="rp-quick-action-desc">{t('pages.suppliers.quickActions.deactivateDesc')}</div>
                        </div>
                    </button>
                )}
            </div>

            <div className="mb-6 grid gap-4 lg:grid-cols-3">
                <div className="rounded-lg border bg-card p-6 lg:col-span-2">
                    <h3 className="mb-4 font-medium">{t('pages.suppliers.sections.details')}</h3>
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-rp-text-muted">{t('pages.suppliers.fields.email')}</dt>
                            <dd>{supplier.email || '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-rp-text-muted">{t('pages.suppliers.fields.phone')}</dt>
                            <dd>{supplier.phone || '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-rp-text-muted">{t('pages.suppliers.columns.balance')}</dt>
                            <dd className="text-lg font-semibold">{supplier.balance}</dd>
                        </div>
                        <div>
                            <dt className="text-rp-text-muted">{t('pages.suppliers.fields.paymentTermsDays')}</dt>
                            <dd>{supplier.payment_terms_days ?? '—'} days</dd>
                        </div>
                        <div>
                            <dt className="text-rp-text-muted">{t('pages.suppliers.fields.creditTermsDays')}</dt>
                            <dd>{supplier.credit_terms_days ?? '—'} days</dd>
                        </div>
                        <div>
                            <dt className="text-rp-text-muted">{t('pages.suppliers.fields.taxRegistrationNo')}</dt>
                            <dd>{supplier.tax_registration_no || '—'}</dd>
                        </div>
                    </dl>
                    {supplier.notes && (
                        <p className="mt-4 text-sm text-rp-text-secondary">{supplier.notes}</p>
                    )}
                </div>

                <div className="rounded-lg border bg-card p-6">
                    <h3 className="mb-4 font-medium">{t('pages.suppliers.performance.title')}</h3>
                    <dl className="space-y-3 text-sm">
                        <div>
                            <dt className="text-rp-text-muted">{t('pages.suppliers.performance.onTimeDelivery')}</dt>
                            <dd className="text-lg font-semibold">
                                {supplier.on_time_delivery_rate != null ? `${supplier.on_time_delivery_rate}%` : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-rp-text-muted">{t('pages.suppliers.performance.qualityRejection')}</dt>
                            <dd className="text-lg font-semibold">
                                {supplier.quality_rejection_rate != null ? `${supplier.quality_rejection_rate}%` : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-rp-text-muted">{t('pages.suppliers.performance.lastScored')}</dt>
                            <dd>{supplier.last_scored_at ? supplier.last_scored_at.slice(0, 10) : '—'}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {can('procurement.process-payments') && branchId && (
                <form onSubmit={recordPayment} className="mb-6 max-w-md rounded-lg border bg-card p-6">
                    <h3 className="mb-4 font-medium">{t('pages.suppliers.recordPayment')}</h3>
                    <div className="space-y-3">
                        <div>
                            <label className="text-xs text-rp-text-muted">{t('pages.suppliers.paymentAmount')}</label>
                            <input
                                type="number"
                                min="0.01"
                                step="any"
                                required
                                className="rp-form-input mt-1 w-full"
                                value={paymentAmount}
                                onChange={(e) => setPaymentAmount(e.target.value)}
                            />
                        </div>
                        <div>
                            <label className="text-xs text-rp-text-muted">{t('pages.suppliers.paymentMethod')}</label>
                            <Select
                                className="mt-1 w-full"
                                value={paymentMethod}
                                onChange={setPaymentMethod}
                                options={paymentMethodOptions}
                            />
                        </div>
                        <div>
                            <label className="text-xs text-rp-text-muted">{t('pages.suppliers.fields.notes')}</label>
                            <input
                                className="rp-form-input mt-1 w-full"
                                value={paymentNotes}
                                onChange={(e) => setPaymentNotes(e.target.value)}
                            />
                        </div>
                        <Button type="submit" className="w-full">
                            {t('pages.suppliers.paySupplier')}
                        </Button>
                    </div>
                </form>
            )}

            {(supplier.contacts?.length > 0 || supplier.addresses?.length > 0) && (
                <div className="mb-6 grid gap-4 lg:grid-cols-2">
                    {supplier.contacts?.length > 0 && (
                        <div className="rounded-lg border bg-card p-4">
                            <h3 className="mb-2 font-medium">Contacts</h3>
                            <ul className="space-y-2 text-sm">
                                {supplier.contacts.map((c) => (
                                    <li key={c.id}>
                                        {c.name} — {c.email || c.phone || '—'}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {supplier.addresses?.length > 0 && (
                        <div className="rounded-lg border bg-card p-4">
                            <h3 className="mb-2 font-medium">Addresses</h3>
                            <ul className="space-y-2 text-sm">
                                {supplier.addresses.map((a) => (
                                    <li key={a.id}>
                                        {a.label}: {a.line1}, {a.city}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}

            {scoreHistory.length > 0 && (
                <div className="mb-6 rounded-lg border bg-card p-6">
                    <h3 className="mb-3 font-medium">{t('pages.suppliers.performance.historyTitle')}</h3>
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b text-muted-foreground">
                                <th className="py-2">{t('pages.suppliers.performance.period')}</th>
                                <th>{t('pages.suppliers.performance.onTimeDelivery')}</th>
                                <th>{t('pages.suppliers.performance.qualityRejection')}</th>
                                <th>{t('pages.suppliers.performance.score')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {scoreHistory.map((row, idx) => (
                                <tr key={idx} className="border-b">
                                    <td className="py-2">
                                        {row.period_start} — {row.period_end}
                                    </td>
                                    <td>{row.on_time_delivery_rate ?? '—'}%</td>
                                    <td>{row.quality_rejection_rate ?? '—'}%</td>
                                    <td>{row.score ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {can('procurement.manage-suppliers') && (
                <SupplierAttachmentSection
                    supplierId={supplier.id}
                    attachments={supplierAttachments}
                />
            )}

            <div className="rounded-lg border bg-card p-6">
                <h3 className="mb-3 font-medium">{t('pages.suppliers.ledgerTitle')}</h3>
                {ledgerEntries.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('pages.suppliers.ledgerEmpty')}</p>
                ) : (
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b text-muted-foreground">
                                <th className="py-2">{t('pages.suppliers.ledgerColumns.date')}</th>
                                <th>{t('pages.suppliers.ledgerColumns.type')}</th>
                                <th>{t('pages.suppliers.ledgerColumns.reference')}</th>
                                <th>{t('pages.suppliers.ledgerColumns.debit')}</th>
                                <th>{t('pages.suppliers.ledgerColumns.balance')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ledgerEntries.map((e) => (
                                <tr key={e.id} className="border-b">
                                    <td className="py-2">{e.created_at?.slice(0, 10)}</td>
                                    <td>{supplierLedgerEntryTypeLabel(t, e.entry_type)}</td>
                                    <td>{e.reference_no}</td>
                                    <td>{e.amount}</td>
                                    <td>{e.balance_after}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </>
    );
}

export default withAdminLayout(Show);
