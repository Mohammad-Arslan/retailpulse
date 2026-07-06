import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import {
    loyaltyTransactionStatusLabel,
    loyaltyTransactionTypeLabel,
} from '@/lib/loyaltyI18n';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ transactions = [], filters = {} }) {
    const { t } = useTranslation();
    const can = useCan();
    const [approveTx, setApproveTx] = useState(null);
    const [rejectTx, setRejectTx] = useState(null);
    const [pin, setPin] = useState('');
    const [rejectReason, setRejectReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const statusFilter = filters.status ?? '';

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.loyalty.transactions.filters.allStatuses') },
            { value: 'pending_approval', label: t('pages.loyalty.transactions.filters.pendingApproval') },
            { value: 'completed', label: t('pages.loyalty.enums.transactionStatuses.completed') },
            { value: 'rejected', label: t('pages.loyalty.enums.transactionStatuses.rejected') },
        ],
        [t],
    );

    function applyStatusFilter(status) {
        router.get(
            route('admin.loyalty.transactions.index'),
            status ? { status } : {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function submitApprove(e) {
        e.preventDefault();
        if (!approveTx) return;
        setProcessing(true);
        router.post(
            route('admin.loyalty.transactions.approve', approveTx.id),
            { pin },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setApproveTx(null);
                    setPin('');
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    function submitReject(e) {
        e.preventDefault();
        if (!rejectTx) return;
        setProcessing(true);
        router.post(
            route('admin.loyalty.transactions.reject', rejectTx.id),
            { reason: rejectReason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRejectTx(null);
                    setRejectReason('');
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <>
            <Head title={t('pages.loyalty.transactions.title')} />
            <PageHeader title={t('pages.loyalty.transactions.title')} description={t('pages.loyalty.transactions.description')}>
                <select
                    value={statusFilter}
                    onChange={(e) => applyStatusFilter(e.target.value)}
                    className="rounded-md border bg-background px-3 py-2 text-sm"
                >
                    {statusOptions.map((opt) => (
                        <option key={opt.value || 'all'} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>
            </PageHeader>

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-muted/30 text-left text-muted-foreground">
                            <th className="px-3 py-2">{t('pages.loyalty.transactions.columns.customer')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.transactions.columns.program')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.transactions.columns.type')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.transactions.columns.points')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.transactions.columns.status')}</th>
                            <th className="px-3 py-2">{t('pages.loyalty.transactions.columns.date')}</th>
                            {can('loyalty.approve') && <th className="px-3 py-2">{t('pages.loyalty.transactions.columns.actions')}</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {transactions.length === 0 ? (
                            <tr>
                                <td colSpan={can('loyalty.approve') ? 7 : 6} className="px-3 py-8 text-center text-muted-foreground">
                                    {t('pages.loyalty.transactions.empty')}
                                </td>
                            </tr>
                        ) : (
                            transactions.map((tx) => (
                                <tr key={tx.id} className="border-b">
                                    <td className="px-3 py-2">{tx.customer}</td>
                                    <td className="px-3 py-2">{tx.program}</td>
                                    <td className="px-3 py-2">{loyaltyTransactionTypeLabel(t, tx.transaction_type)}</td>
                                    <td className="px-3 py-2">{tx.points}</td>
                                    <td className="px-3 py-2">{loyaltyTransactionStatusLabel(t, tx.status)}</td>
                                    <td className="px-3 py-2">
                                        {tx.created_at ? new Date(tx.created_at).toLocaleString() : '—'}
                                    </td>
                                    {can('loyalty.approve') && (
                                        <td className="px-3 py-2">
                                            {tx.status === 'pending_approval' && (
                                                <div className="flex gap-2">
                                                    <Button type="button" size="sm" variant="outline" onClick={() => setApproveTx(tx)}>
                                                        {t('pages.loyalty.transactions.actions.approve')}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => setRejectTx(tx)}
                                                    >
                                                        {t('pages.loyalty.transactions.actions.reject')}
                                                    </Button>
                                                </div>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {approveTx !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                    <form
                        onSubmit={submitApprove}
                        className="w-full max-w-md rounded-xl border bg-card p-6 shadow-xl"
                    >
                        <h3 className="text-lg font-semibold">{t('pages.loyalty.transactions.approveTitle')}</h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('pages.loyalty.transactions.approveDescription')}
                        </p>
                        <div className="mt-4">
                            <label className="mb-1 block text-sm font-medium" htmlFor="approve-pin">
                                {t('pages.loyalty.transactions.pin')}
                            </label>
                            <input
                                id="approve-pin"
                                type="password"
                                inputMode="numeric"
                                maxLength={4}
                                value={pin}
                                onChange={(e) => setPin(e.target.value)}
                                className="rp-form-input w-full"
                                required
                            />
                        </div>
                        <div className="mt-6 flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setApproveTx(null)}>
                                {t('pages.loyalty.actions.cancel')}
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {t('pages.loyalty.transactions.actions.approve')}
                            </Button>
                        </div>
                    </form>
                </div>
            )}

            {rejectTx !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                    <form
                        onSubmit={submitReject}
                        className="w-full max-w-md rounded-xl border bg-card p-6 shadow-xl"
                    >
                        <h3 className="text-lg font-semibold">{t('pages.loyalty.transactions.rejectTitle')}</h3>
                        <div className="mt-4">
                            <label className="mb-1 block text-sm font-medium" htmlFor="reject-reason">
                                {t('pages.loyalty.transactions.rejectReason')}
                            </label>
                            <textarea
                                id="reject-reason"
                                value={rejectReason}
                                onChange={(e) => setRejectReason(e.target.value)}
                                className="rp-form-input w-full"
                                rows={3}
                            />
                        </div>
                        <div className="mt-6 flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setRejectTx(null)}>
                                {t('pages.loyalty.actions.cancel')}
                            </Button>
                            <Button type="submit" variant="destructive" disabled={processing}>
                                {t('pages.loyalty.transactions.actions.reject')}
                            </Button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}

export default withAdminLayout(Index);
