import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Mail, Pencil, Plus, Wallet } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';

function StatCard({ label, value, hint }) {
    return (
        <div className="rounded-lg border bg-card p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-semibold">{value}</p>
            {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

function Show({
    customer,
    stats = {},
    recentSales = [],
    walletTransactions = [],
    arLedger = [],
    currency = 'PKR',
    canViewCredit = false,
    loyalty = { enabled: false, wallets: [], transactions: [], timeline: [] },
    loyaltyPrograms = [],
}) {
    const can = useCan();
    const { t } = useTranslation();
    const [topUpOpen, setTopUpOpen] = useState(false);
    const [activeTab, setActiveTab] = useState('overview');
    const [topUpAmount, setTopUpAmount] = useState('');
    const [topUpMethod, setTopUpMethod] = useState('cash');
    const [topUpProcessing, setTopUpProcessing] = useState(false);
    const [statementSending, setStatementSending] = useState(false);

    const tierName = customer.loyalty_tier?.name;
    const walletBalance = customer.wallet?.balance ?? '0.00';

    const topUpMethodOptions = useMemo(
        () => [
            { value: 'cash', label: t('checkout.paymentMethods.cash') },
            { value: 'card', label: t('checkout.paymentMethods.card') },
            { value: 'bank_transfer', label: t('checkout.paymentMethods.bank_transfer') },
        ],
        [t],
    );

    async function handleTopUp(e) {
        e.preventDefault();
        const amount = parseFloat(topUpAmount);
        if (!amount || amount <= 0) {
            toast.error(t('pages.customers.topUp.invalidAmount'));
            return;
        }

        setTopUpProcessing(true);
        router.post(
            route('admin.customers.wallet.top-up', customer.id),
            {
                amount,
                payment_method: topUpMethod,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(t('pages.customers.topUp.success'));
                    setTopUpOpen(false);
                    setTopUpAmount('');
                },
                onError: (errors) => {
                    const message =
                        errors.amount
                        ?? errors.payment_method
                        ?? Object.values(errors)[0]
                        ?? t('pages.customers.topUp.failed');
                    toast.error(message);
                },
                onFinish: () => setTopUpProcessing(false),
            },
        );
    }

    async function handleSendStatement() {
        setStatementSending(true);
        try {
            router.post(
                route('admin.customers.send-statement', customer.id),
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => toast.success(t('pages.customers.statementSent')),
                    onError: () => toast.error(t('pages.customers.statementFailed')),
                    onFinish: () => setStatementSending(false),
                },
            );
        } catch {
            setStatementSending(false);
        }
    }

    return (
        <>
            <Head title={customer.name} />
            <PageHeader
                title={customer.name}
                description={t('pages.customers.showDescription')}
            >
                <div className="flex flex-wrap items-center gap-2">
                    {can('customers.update') && (
                        <Link href={route('admin.customers.edit', customer.id)} className="rp-btn-outline">
                            <Pencil className="h-4 w-4" />
                            {t('common.edit')}
                        </Link>
                    )}
                    {can('customers.view-credit') && customer.email && (
                        <Button variant="outline" onClick={handleSendStatement} disabled={statementSending}>
                            <Mail className="mr-2 h-4 w-4" />
                            {t('pages.customers.sendStatement')}
                        </Button>
                    )}
                    <Link href={route('admin.customers.index')} className="rp-btn-outline">
                        {t('pages.customers.back')}
                    </Link>
                </div>
            </PageHeader>

            <div className="mb-6 flex flex-wrap items-center gap-3">
                {tierName && (
                    <span className="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-200">
                        {tierName}
                        {customer.loyalty_tier?.points_multiplier && (
                            <span className="ml-1 text-xs opacity-80">
                                ×{customer.loyalty_tier.points_multiplier}
                            </span>
                        )}
                    </span>
                )}
                {customer.customer_group?.name && (
                    <span className="rounded-full bg-sky-100 px-3 py-1 text-sm font-medium text-sky-800 dark:bg-sky-500/20 dark:text-sky-200">
                        {customer.customer_group.name}
                    </span>
                )}
                {!customer.is_active && (
                    <span className="rounded-full bg-zinc-100 px-3 py-1 text-sm text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        {t('pages.customers.inactive')}
                    </span>
                )}
            </div>

            <div className="mb-6 flex gap-2 border-b">
                <button
                    type="button"
                    onClick={() => setActiveTab('overview')}
                    className={`px-4 py-2 text-sm font-medium ${activeTab === 'overview' ? 'border-b-2 border-teal-600 text-teal-600' : 'text-muted-foreground'}`}
                >
                    {t('pages.customers.tabs.overview')}
                </button>
                {loyalty.enabled && (
                    <button
                        type="button"
                        onClick={() => setActiveTab('loyalty')}
                        className={`px-4 py-2 text-sm font-medium ${activeTab === 'loyalty' ? 'border-b-2 border-teal-600 text-teal-600' : 'text-muted-foreground'}`}
                    >
                        {t('pages.customers.tabs.loyalty')}
                    </button>
                )}
            </div>

            {activeTab === 'loyalty' && loyalty.enabled && (
                <div className="mb-8 space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {(loyalty.wallets ?? []).map((wallet, idx) => (
                            <StatCard
                                key={idx}
                                label={wallet.program ?? t('pages.customers.loyalty.program')}
                                value={wallet.available_points ?? 0}
                                hint={wallet.tier?.name ? `${t('pages.customers.loyalty.tier')}: ${wallet.tier.name}` : undefined}
                            />
                        ))}
                    </div>
                    {(loyalty.wallets ?? []).length > 0 && (
                        <div className="grid gap-4 sm:grid-cols-3">
                            <StatCard label={t('pages.customers.loyalty.lifetimeEarned')} value={loyalty.wallets[0]?.lifetime_earned_points ?? 0} />
                            <StatCard label={t('pages.customers.loyalty.redeemed')} value={loyalty.wallets[0]?.redeemed_points ?? 0} />
                            <StatCard label={t('pages.customers.loyalty.expired')} value={loyalty.wallets[0]?.expired_points ?? 0} />
                        </div>
                    )}
                    <section>
                        <h2 className="mb-3 font-semibold">{t('pages.customers.loyalty.recentTransactions')}</h2>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/30 text-left text-muted-foreground">
                                        <th className="px-3 py-2">{t('pages.customers.columns.date')}</th>
                                        <th className="px-3 py-2">{t('pages.customers.columns.type')}</th>
                                        <th className="px-3 py-2">{t('pages.customers.loyalty.points')}</th>
                                        <th className="px-3 py-2">{t('pages.customers.loyalty.status')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(loyalty.transactions ?? []).length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="px-3 py-6 text-center text-muted-foreground">
                                                {t('pages.customers.loyalty.noTransactions')}
                                            </td>
                                        </tr>
                                    ) : (
                                        loyalty.transactions.map((tx) => (
                                            <tr key={tx.id} className="border-b">
                                                <td className="px-3 py-2">{tx.created_at ? new Date(tx.created_at).toLocaleString() : '—'}</td>
                                                <td className="px-3 py-2 capitalize">{tx.type?.replace('_', ' ')}</td>
                                                <td className="px-3 py-2">{tx.points > 0 ? `+${tx.points}` : tx.points}</td>
                                                <td className="px-3 py-2 capitalize">{tx.status?.replace('_', ' ')}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section>
                        <h2 className="mb-3 font-semibold">{t('pages.customers.loyalty.timeline')}</h2>
                        <ul className="space-y-2 rounded-lg border p-4">
                            {(loyalty.timeline ?? []).length === 0 ? (
                                <li className="text-sm text-muted-foreground">{t('pages.customers.loyalty.noTimeline')}</li>
                            ) : (
                                loyalty.timeline.map((event) => (
                                    <li key={event.id} className="flex items-start justify-between gap-4 border-b pb-2 text-sm last:border-0">
                                        <div>
                                            <p className="font-medium capitalize">{event.event_type?.replace('_', ' ')}</p>
                                            <p className="text-muted-foreground">{event.description}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className={event.points >= 0 ? 'text-teal-600' : 'text-amber-600'}>
                                                {event.points > 0 ? `+${event.points}` : event.points}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {event.created_at ? new Date(event.created_at).toLocaleString() : ''}
                                            </p>
                                        </div>
                                    </li>
                                ))
                            )}
                        </ul>
                    </section>
                </div>
            )}

            {activeTab === 'overview' && (
            <>
            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label={t('pages.customers.stats.atv')}
                    value={`${stats.atv ?? '0.00'} ${currency}`}
                />
                <StatCard
                    label={t('pages.customers.stats.salesCount')}
                    value={stats.sales_count ?? 0}
                />
                <StatCard
                    label={t('pages.customers.stats.loyaltyPoints')}
                    value={customer.loyalty_points ?? 0}
                />
                {canViewCredit && (
                    <StatCard
                        label={t('pages.customers.stats.creditAvailable')}
                        value={`${customer.credit_available ?? '0.00'} ${currency}`}
                        hint={
                            customer.credit_limit != null
                                ? t('pages.customers.stats.creditLimitHint', {
                                      limit: customer.credit_limit,
                                      currency,
                                  })
                                : undefined
                        }
                    />
                )}
            </div>

            <div className="mb-8 grid gap-4 lg:grid-cols-3">
                <div className="rounded-lg border bg-card p-4">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="font-semibold">{t('pages.customers.wallet.title')}</h2>
                        {can('customers.update') && (
                            <Button size="sm" variant="outline" onClick={() => setTopUpOpen(true)}>
                                <Plus className="mr-1 h-3.5 w-3.5" />
                                {t('pages.customers.wallet.topUp')}
                            </Button>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        <Wallet className="h-8 w-8 text-teal-600" />
                        <div>
                            <p className="text-2xl font-semibold">
                                {walletBalance} {currency}
                            </p>
                            {customer.wallet?.expires_at && (
                                <p className="text-xs text-muted-foreground">
                                    {t('pages.customers.wallet.expires', {
                                        date: new Date(customer.wallet.expires_at).toLocaleDateString(),
                                    })}
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                {canViewCredit && (
                    <>
                        <div className="rounded-lg border bg-card p-4">
                            <h2 className="mb-2 font-semibold">{t('pages.customers.storeCredit.title')}</h2>
                            <p className="text-2xl font-semibold">
                                {customer.store_credit_balance ?? '0.00'} {currency}
                            </p>
                        </div>
                        <div className="rounded-lg border bg-card p-4">
                            <h2 className="mb-2 font-semibold">{t('pages.customers.arOutstanding.title')}</h2>
                            <p className="text-2xl font-semibold text-amber-600">
                                {customer.ar_outstanding ?? '0.00'} {currency}
                            </p>
                        </div>
                    </>
                )}
            </div>

            <div className="mb-8 rounded-lg border bg-card p-4 text-sm">
                <h2 className="mb-3 font-semibold">{t('pages.customers.contact')}</h2>
                <dl className="grid gap-2 sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground">{t('pages.customers.fields.phone')}</dt>
                        <dd>{customer.phone ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">{t('pages.customers.fields.email')}</dt>
                        <dd>{customer.email ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">{t('pages.customers.fields.ntn')}</dt>
                        <dd>{customer.ntn ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">{t('pages.customers.fields.cnic')}</dt>
                        <dd>{customer.cnic ?? '—'}</dd>
                    </div>
                </dl>
                {customer.notes && (
                    <p className="mt-3 text-muted-foreground">{customer.notes}</p>
                )}
            </div>

            <section className="mb-8">
                <h2 className="mb-3 font-semibold">{t('pages.customers.recentSales')}</h2>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/30 text-left text-muted-foreground">
                                <th className="px-3 py-2">{t('pages.sales.columns.invoice')}</th>
                                <th className="px-3 py-2">{t('pages.sales.columns.status')}</th>
                                <th className="px-3 py-2">{t('pages.sales.columns.total')}</th>
                                <th className="px-3 py-2">{t('pages.sales.columns.completed')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {recentSales.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-3 py-6 text-center text-muted-foreground">
                                        {t('pages.customers.noSales')}
                                    </td>
                                </tr>
                            ) : (
                                recentSales.map((sale) => (
                                    <tr key={sale.id} className="border-b">
                                        <td className="px-3 py-2">
                                            <Link
                                                href={route('admin.sales.show', sale.id)}
                                                className="font-medium text-teal-600 hover:underline"
                                            >
                                                {sale.invoice?.number ?? `#${sale.id}`}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2 capitalize">
                                            {sale.status?.replace('_', ' ')}
                                        </td>
                                        <td className="px-3 py-2">
                                            {sale.grand_total} {sale.currency ?? currency}
                                        </td>
                                        <td className="px-3 py-2">
                                            {sale.completed_at
                                                ? new Date(sale.completed_at).toLocaleString()
                                                : '—'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <div className="grid gap-6 lg:grid-cols-2">
                <section>
                    <h2 className="mb-3 font-semibold">{t('pages.customers.walletTransactions')}</h2>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/30 text-left text-muted-foreground">
                                    <th className="px-3 py-2">{t('pages.customers.columns.date')}</th>
                                    <th className="px-3 py-2">{t('pages.customers.columns.type')}</th>
                                    <th className="px-3 py-2 text-right">{t('pages.customers.columns.amount')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {walletTransactions.length === 0 ? (
                                    <tr>
                                        <td colSpan={3} className="px-3 py-6 text-center text-muted-foreground">
                                            {t('pages.customers.noWalletTransactions')}
                                        </td>
                                    </tr>
                                ) : (
                                    walletTransactions.map((tx) => (
                                        <tr key={tx.id} className="border-b">
                                            <td className="px-3 py-2">
                                                {tx.created_at
                                                    ? new Date(tx.created_at).toLocaleString()
                                                    : '—'}
                                            </td>
                                            <td className="px-3 py-2 capitalize">
                                                {tx.type} · {tx.reason?.replace('_', ' ')}
                                            </td>
                                            <td
                                                className={`px-3 py-2 text-right font-medium ${
                                                    tx.type === 'credit' ? 'text-green-600' : 'text-red-600'
                                                }`}
                                            >
                                                {tx.type === 'credit' ? '+' : '-'}
                                                {tx.amount} {currency}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {canViewCredit && (
                    <section>
                        <h2 className="mb-3 font-semibold">{t('pages.customers.arLedger')}</h2>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/30 text-left text-muted-foreground">
                                        <th className="px-3 py-2">{t('pages.customers.columns.date')}</th>
                                        <th className="px-3 py-2">{t('pages.customers.columns.entryType')}</th>
                                        <th className="px-3 py-2 text-right">{t('pages.customers.columns.amount')}</th>
                                        <th className="px-3 py-2 text-right">{t('pages.customers.columns.balance')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {arLedger.length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="px-3 py-6 text-center text-muted-foreground">
                                                {t('pages.customers.noArLedger')}
                                            </td>
                                        </tr>
                                    ) : (
                                        arLedger.map((entry) => (
                                            <tr key={entry.id} className="border-b">
                                                <td className="px-3 py-2">
                                                    {entry.created_at
                                                        ? new Date(entry.created_at).toLocaleString()
                                                        : '—'}
                                                </td>
                                                <td className="px-3 py-2 capitalize">
                                                    {entry.entry_type?.replace('_', ' ')}
                                                </td>
                                                <td className="px-3 py-2 text-right">{entry.amount}</td>
                                                <td className="px-3 py-2 text-right font-medium">
                                                    {entry.balance_after}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </div>
            </>
            )}

            {topUpOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                    <form
                        onSubmit={handleTopUp}
                        className="w-full max-w-md rounded-xl border bg-card p-6 shadow-xl"
                    >
                        <h3 className="text-lg font-semibold">{t('pages.customers.topUp.title')}</h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('pages.customers.topUp.description', { name: customer.name })}
                        </p>
                        <div className="mt-4 space-y-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium">
                                    {t('pages.customers.topUp.amount')}
                                </label>
                                <input
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={topUpAmount}
                                    onChange={(e) => setTopUpAmount(e.target.value)}
                                    className="rp-form-input w-full"
                                    required
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium">
                                    {t('pages.customers.topUp.method')}
                                </label>
                                <Select
                                    id="top_up_method"
                                    options={topUpMethodOptions}
                                    value={topUpMethod}
                                    onChange={(value) => setTopUpMethod(value ?? 'cash')}
                                />
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setTopUpOpen(false)}>
                                {t('confirm.cancel')}
                            </Button>
                            <Button type="submit" disabled={topUpProcessing}>
                                {t('pages.customers.wallet.topUp')}
                            </Button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}

export default withAdminLayout(Show);
