import AdminFormField from '@/Components/common/AdminFormField';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { prStatusLabel } from '@/lib/procurementI18n';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle, Package, Send, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

const statusClass = {
    draft: 'bg-stone-100 text-stone-700',
    submitted: 'bg-amber-100 text-amber-800',
    approved: 'bg-teal-100 text-teal-800',
    rejected: 'bg-rose-100 text-rose-700',
    cancelled: 'bg-stone-100 text-stone-500',
    converted: 'bg-violet-100 text-violet-800',
};

function Show({ purchaseRequest: pr, config, approval = {}, suppliers = [], can: actionCan = {} }) {
    const can = useCan();
    const { t } = useTranslation();
    const { errors: serverErrors } = usePage().props;
    const [managerPin, setManagerPin] = useState('');
    const [pinError, setPinError] = useState('');
    const [rejectionReason, setRejectionReason] = useState('');
    const [showReject, setShowReject] = useState(false);
    const [showConvert, setShowConvert] = useState(false);
    const [supplierId, setSupplierId] = useState('');

    const needsPin = approval.requiresPin ?? Number(pr.total) >= Number(config?.pr_approval_threshold ?? 0);
    const approverHasPin = approval.approverHasPin ?? true;

    const supplierOptions = useMemo(
        () => suppliers.map((s) => ({ value: String(s.id), label: s.name })),
        [suppliers],
    );

    const submitPr = () => router.post(route('admin.purchase-requests.submit', pr.id));
    const approvePr = () => {
        if (needsPin) {
            if (!approverHasPin) {
                setPinError(t('pages.purchaseRequests.noPinConfigured'));
                return;
            }
            if (!/^\d{6}$/.test(managerPin)) {
                setPinError(t('pages.purchaseRequests.pinRequired'));
                return;
            }
        }
        setPinError('');
        router.post(route('admin.purchase-requests.approve', pr.id), { manager_pin: managerPin || null });
    };
    const rejectPr = () =>
        router.post(route('admin.purchase-requests.reject', pr.id), { rejection_reason: rejectionReason });
    const cancelPr = () => {
        if (!window.confirm(t('pages.purchaseRequests.cancelConfirm', { reference: pr.reference_no }))) {
            return;
        }
        router.post(route('admin.purchase-requests.cancel', pr.id));
    };
    const convertPr = () => {
        if (!supplierId) {
            return;
        }
        router.post(route('admin.purchase-requests.convert', pr.id), { supplier_id: Number(supplierId) });
    };

    const hasWorkflowActions =
        actionCan.submit ||
        actionCan.approve ||
        actionCan.reject ||
        actionCan.cancel ||
        actionCan.convert ||
        (pr.status === 'draft' && can('procurement.create')) ||
        (pr.status === 'submitted' && can('procurement.approve-pr')) ||
        (pr.status === 'approved' && can('procurement.convert-pr'));

    return (
        <>
            <Head title={pr.reference_no} />
            <PageHeader
                title={pr.reference_no}
                description={
                    pr.warehouse?.name
                        ? `${pr.warehouse.name}`
                        : t('pages.purchaseRequests.description')
                }
            >
                <Link href={route('admin.purchase-requests.index')} className="rp-btn-outline">
                    {t('pages.purchaseRequests.backToList')}
                </Link>
            </PageHeader>

            <div className="mb-6 flex flex-wrap items-center gap-3">
                <span
                    className={`inline-flex rounded-full px-3 py-1 text-sm font-medium capitalize ${statusClass[pr.status] ?? ''}`}
                >
                    {prStatusLabel(t, pr.status)}
                </span>
                <span className="text-sm text-rp-text-muted">
                    {t('pages.purchaseRequests.totalLabel')}:{' '}
                    <strong className="text-rp-text">{pr.total}</strong> {pr.currency_code}
                </span>
                {pr.needed_by && (
                    <span className="text-sm text-rp-text-muted">
                        {t('pages.purchaseRequests.fields.neededBy')}: {pr.needed_by}
                    </span>
                )}
                {pr.converted_purchase_order && (
                    <Link
                        href={route('admin.purchase-orders.show', pr.converted_purchase_order.id)}
                        className="text-sm text-teal-600 hover:underline"
                    >
                        {t('pages.purchaseRequests.convertedPo')}: {pr.converted_purchase_order.reference_no}
                    </Link>
                )}
            </div>

            {pr.notes && (
                <div className="mb-6 rounded-lg border bg-card p-5">
                    <h3 className="mb-2 font-medium">{t('pages.purchaseRequests.fields.notes')}</h3>
                    <p className="whitespace-pre-wrap text-sm text-rp-text-muted">{pr.notes}</p>
                </div>
            )}

            {pr.rejection_reason && (
                <div className="mb-6 rounded-lg border border-rose-200 bg-rose-50 p-5 dark:border-rose-500/40 dark:bg-rose-500/10">
                    <h3 className="mb-2 font-medium text-rose-800 dark:text-rose-200">
                        {t('pages.purchaseRequests.rejection')}
                    </h3>
                    <p className="text-sm text-rose-700 dark:text-rose-300">{pr.rejection_reason}</p>
                </div>
            )}

            {hasWorkflowActions && (
                <div className="mb-6 rounded-lg border bg-card p-5">
                    <h3 className="mb-4 font-medium">{t('pages.purchaseRequests.sections.actions')}</h3>

                    {pr.status === 'submitted' && needsPin && (actionCan.approve || can('procurement.approve-pr')) && (
                        <div className="mb-4 rounded-lg border border-amber-300/60 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10">
                            <AdminFormField
                                label={t('pages.purchaseRequests.fields.managerPin')}
                                id="manager_pin"
                                error={pinError || serverErrors?.manager_pin}
                                hint={
                                    approverHasPin
                                        ? t('pages.purchaseRequests.pinHint')
                                        : t('pages.purchaseRequests.noPinConfigured')
                                }
                            >
                                <input
                                    id="manager_pin"
                                    className="rp-form-input w-full max-w-sm tracking-[0.35em] font-mono text-lg"
                                    type="password"
                                    inputMode="numeric"
                                    autoComplete="off"
                                    pattern="\d{6}"
                                    maxLength={6}
                                    placeholder={t('pages.purchaseRequests.placeholders.managerPin')}
                                    value={managerPin}
                                    onChange={(e) => {
                                        setManagerPin(e.target.value.replace(/\D/g, '').slice(0, 6));
                                        if (pinError) setPinError('');
                                    }}
                                    disabled={!approverHasPin}
                                />
                            </AdminFormField>
                        </div>
                    )}

                    <div className="flex flex-wrap gap-2">
                        {(actionCan.submit || (pr.status === 'draft' && can('procurement.create'))) &&
                            pr.status === 'draft' && (
                                <Button type="button" onClick={submitPr}>
                                    <Send className="h-4 w-4" />
                                    {t('pages.purchaseRequests.actions.submit')}
                                </Button>
                            )}
                        {(actionCan.reject || can('procurement.approve-pr')) && pr.status === 'submitted' && (
                            <Button type="button" onClick={() => setShowReject((v) => !v)} variant="outline">
                                <XCircle className="h-4 w-4" />
                                {t('pages.purchaseRequests.actions.reject')}
                            </Button>
                        )}
                        {(actionCan.approve || can('procurement.approve-pr')) && pr.status === 'submitted' && (
                            <Button type="button" onClick={approvePr}>
                                <CheckCircle className="h-4 w-4" />
                                {t('pages.purchaseRequests.actions.approve')}
                            </Button>
                        )}
                        {(actionCan.convert || can('procurement.convert-pr')) && pr.status === 'approved' && (
                            <Button type="button" onClick={() => setShowConvert((v) => !v)}>
                                <Package className="h-4 w-4" />
                                {t('pages.purchaseRequests.actions.convert')}
                            </Button>
                        )}
                        {(actionCan.cancel || can('procurement.update')) &&
                            ['draft', 'submitted', 'approved'].includes(pr.status) && (
                                <Button type="button" variant="destructive" onClick={cancelPr}>
                                    {t('pages.purchaseRequests.actions.cancel')}
                                </Button>
                            )}
                    </div>

                    {showReject && pr.status === 'submitted' && (
                        <div className="mt-4 space-y-3 border-t pt-4">
                            <AdminFormField
                                label={t('pages.purchaseRequests.fields.rejectionReason')}
                                error={serverErrors?.rejection_reason}
                            >
                                <textarea
                                    className="rp-form-input min-h-[80px]"
                                    value={rejectionReason}
                                    onChange={(e) => setRejectionReason(e.target.value)}
                                />
                            </AdminFormField>
                            <Button type="button" variant="destructive" onClick={rejectPr}>
                                {t('pages.purchaseRequests.confirmReject')}
                            </Button>
                        </div>
                    )}

                    {showConvert && pr.status === 'approved' && (
                        <div className="mt-4 space-y-3 border-t pt-4">
                            <p className="text-sm text-rp-text-muted">{t('pages.purchaseRequests.convertHint')}</p>
                            <AdminFormField
                                label={t('pages.purchaseRequests.fields.supplier')}
                                error={serverErrors?.supplier_id}
                            >
                                <Select
                                    options={supplierOptions}
                                    value={supplierId}
                                    onChange={(value) => setSupplierId(value ?? '')}
                                    placeholder={t('pages.purchaseRequests.placeholders.supplier')}
                                />
                            </AdminFormField>
                            <Button type="button" onClick={convertPr} disabled={!supplierId}>
                                {t('pages.purchaseRequests.actions.convert')}
                            </Button>
                        </div>
                    )}
                </div>
            )}

            <div className="rounded-lg border bg-card p-5">
                <h3 className="mb-3 font-medium">{t('pages.purchaseRequests.sections.lineItems')}</h3>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-rp-text-muted">
                                <th className="py-2">{t('pages.purchaseRequests.lineColumns.sku')}</th>
                                <th>{t('pages.purchaseRequests.lineColumns.product')}</th>
                                <th>{t('pages.purchaseRequests.lineColumns.qty')}</th>
                                <th>{t('pages.purchaseRequests.lineColumns.estimatedCost')}</th>
                                <th>{t('pages.purchaseRequests.lineColumns.lineTotal')}</th>
                                <th>{t('pages.purchaseRequests.fields.preferredSupplier')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(pr.items ?? []).map((item) => (
                                <tr key={item.id} className="border-b border-rp-border/60">
                                    <td className="py-2 font-mono text-xs">{item.variant?.sku ?? '—'}</td>
                                    <td>
                                        <div>{item.variant?.name ?? '—'}</div>
                                        {item.notes && (
                                            <div className="text-xs text-rp-text-muted">{item.notes}</div>
                                        )}
                                    </td>
                                    <td>{item.qty}</td>
                                    <td>{item.estimated_unit_cost}</td>
                                    <td>{item.line_total}</td>
                                    <td>{item.preferred_supplier?.name ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

export default withAdminLayout(Show);
