import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

function Show({ expense }) {
    const { t } = useTranslation();
    const can = useCan();

    const approveForm = useForm({});
    const receiptForm = useForm({ receipt: null });

    const canApprove =
        (expense.status === 'pending_approval' ||
            (expense.status === 'draft' && !expense.approval_required)) &&
        can('expenses.approve');

    const rows = [
        [t('pages.expenses.fields.number'), expense.expense_number],
        [t('pages.expenses.fields.category'), expense.category ?? '—'],
        [t('pages.expenses.fields.branch'), expense.branch ?? '—'],
        [t('pages.expenses.fields.legalEntity'), expense.legal_entity ?? '—'],
        [t('pages.expenses.fields.costCentre'), expense.cost_centre ?? '—'],
        [
            t('pages.expenses.fields.amount'),
            `${expense.currency_code} ${Number(expense.amount).toFixed(2)}`,
        ],
        [t('pages.expenses.fields.taxType'), expense.tax_type ?? '—'],
        [t('pages.expenses.fields.taxAmount'), expense.tax_amount ?? '0'],
        [t('pages.expenses.fields.functionalAmount'), expense.functional_amount ?? '—'],
        [t('pages.expenses.fields.date'), expense.expense_date ?? '—'],
        [
            t('pages.expenses.fields.paymentMethod'),
            expense.payment_method
                ? t(`pages.expenses.paymentMethods.${expense.payment_method}`, {
                      defaultValue: expense.payment_method,
                  })
                : t('pages.expenses.noPaymentMethod'),
        ],
        [
            t('pages.expenses.fields.status'),
            t(`pages.expenses.statuses.${expense.status}`, { defaultValue: expense.status }),
        ],
        [
            t('pages.expenses.fields.approvalRequired'),
            expense.approval_required ? t('common.yes') : t('common.no'),
        ],
        [t('pages.expenses.fields.approvedBy'), expense.approved_by ?? '—'],
        [t('pages.expenses.fields.approvedAt'), expense.approved_at ?? '—'],
        [t('pages.expenses.fields.journalEntry'), expense.journal_entry_id ?? '—'],
        [t('pages.expenses.fields.description'), expense.description ?? '—'],
    ];

    const approve = () => {
        approveForm.post(route('admin.expenses.expenses.approve', expense.id));
    };

    const attachReceipt = (e) => {
        e.preventDefault();
        receiptForm.post(route('admin.expenses.expenses.attachments', expense.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => receiptForm.reset(),
        });
    };

    return (
        <>
            <Head title={expense.expense_number} />
            <PageHeader title={expense.expense_number} description={expense.category ?? ''}>
                <Link href={route('admin.expenses.expenses.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
                {canApprove && (
                    <Button type="button" onClick={approve} disabled={approveForm.processing}>
                        {t('pages.expenses.approve')}
                    </Button>
                )}
            </PageHeader>

            <dl className="mb-8 grid max-w-3xl gap-4 sm:grid-cols-2">
                {rows.map(([label, value]) => (
                    <div key={label} className="rounded-lg border border-rp-border p-3">
                        <dt className="text-xs text-rp-text-muted">{label}</dt>
                        <dd className="mt-1 text-sm font-medium text-rp-text">{value}</dd>
                    </div>
                ))}
            </dl>

            <section className="mb-8 max-w-3xl">
                <h2 className="mb-3 text-sm font-semibold text-rp-text">
                    {t('pages.expenses.fields.attachments')}
                </h2>
                {expense.attachments?.length > 0 ? (
                    <ul className="mb-4 space-y-2">
                        {expense.attachments.map((a) => (
                            <li key={a.id} className="rounded border border-rp-border px-3 py-2 text-sm">
                                {a.original_name}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="mb-4 text-sm text-rp-text-muted">—</p>
                )}

                {can('expenses.create') && expense.status !== 'posted' && (
                    <form onSubmit={attachReceipt} className="flex flex-wrap items-end gap-3">
                        <div>
                            <label className="rp-label">{t('pages.expenses.receiptLabel')}</label>
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                onChange={(e) => receiptForm.setData('receipt', e.target.files?.[0] ?? null)}
                                className="block text-sm"
                            />
                            {receiptForm.errors.receipt && (
                                <p className="text-xs text-red-600">{receiptForm.errors.receipt}</p>
                            )}
                        </div>
                        <Button type="submit" disabled={receiptForm.processing || !receiptForm.data.receipt}>
                            {t('pages.expenses.attachReceipt')}
                        </Button>
                    </form>
                )}
            </section>
        </>
    );
}

export default withAdminLayout(Show);
