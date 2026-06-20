import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/Components/ui/button';

function Show({ sale }) {
    const { t } = useTranslation();

    const handlePrint = () => {
        const token = sale.invoice?.public_token;
        if (!token) return;
        window.open(route('invoice.print', { publicToken: token }), '_blank');
    };

    return (
        <>
            <Head title={sale.invoice?.number ?? `Sale #${sale.id}`} />
            <PageHeader
                title={sale.invoice?.number ?? `Sale #${sale.id}`}
                description={t('pages.sales.showDescription')}
            >
                <Link href={route('admin.sales.index')} className="rp-btn-outline">
                    {t('pages.sales.back')}
                </Link>
                {sale.invoice?.public_token && (
                    <Button variant="outline" onClick={handlePrint}>
                        <Printer className="mr-2 h-4 w-4" />
                        {t('pages.sales.printInvoice')}
                    </Button>
                )}
            </PageHeader>

            <div className="grid gap-6 lg:grid-cols-3">
                <section className="rounded-lg border bg-card p-4 lg:col-span-2">
                    <h2 className="mb-3 font-semibold">{t('pages.sales.lineItems')}</h2>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2">{t('pages.sales.columns.item')}</th>
                                <th className="py-2">{t('pages.sales.columns.qty')}</th>
                                <th className="py-2 text-right">{t('pages.sales.columns.total')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sale.items.map((item) => (
                                <tr key={`${item.sku}-${item.name}`} className="border-b">
                                    <td className="py-2">
                                        <div className="font-medium">{item.name}</div>
                                        <div className="text-xs text-muted-foreground">{item.sku}</div>
                                    </td>
                                    <td className="py-2">{item.quantity}</td>
                                    <td className="py-2 text-right">
                                        {item.line_total_inc_tax} {sale.currency}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                <section className="space-y-4">
                    <div className="rounded-lg border bg-card p-4 text-sm">
                        <h2 className="mb-3 font-semibold">{t('pages.sales.summary')}</h2>
                        <div className="space-y-1">
                            <div className="flex justify-between">
                                <span>{t('pages.sales.subtotal')}</span>
                                <span>{sale.subtotal} {sale.currency}</span>
                            </div>
                            {parseFloat(sale.total_discount) > 0 && (
                                <div className="flex justify-between text-green-600">
                                    <span>{t('pages.sales.discount')}</span>
                                    <span>-{sale.total_discount}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-muted-foreground">
                                <span>{t('pages.sales.tax')}</span>
                                <span>{sale.tax_total}</span>
                            </div>
                            <div className="flex justify-between text-lg font-semibold">
                                <span>{t('pages.sales.total')}</span>
                                <span>{sale.grand_total} {sale.currency}</span>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-lg border bg-card p-4 text-sm">
                        <h2 className="mb-3 font-semibold">{t('pages.sales.details')}</h2>
                        <dl className="space-y-2">
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">{t('pages.sales.columns.status')}</dt>
                                <dd className="capitalize">{sale.status?.replace('_', ' ')}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">{t('pages.sales.columns.cashier')}</dt>
                                <dd>{sale.cashier?.name ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">{t('pages.sales.columns.customer')}</dt>
                                <dd>{sale.customer?.name ?? '—'}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">{t('pages.sales.columns.branch')}</dt>
                                <dd>{sale.branch?.name ?? '—'}</dd>
                            </div>
                        </dl>
                    </div>

                    {sale.payments?.length > 0 && (
                        <div className="rounded-lg border bg-card p-4 text-sm">
                            <h2 className="mb-3 font-semibold">{t('pages.sales.payments')}</h2>
                            <div className="space-y-2">
                                {sale.payments.map((payment, index) => (
                                    <div key={index} className="flex justify-between rounded border px-2 py-1">
                                        <span className="capitalize">{payment.method?.replace('_', ' ')}</span>
                                        <span>{payment.amount} {sale.currency}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

export default withAdminLayout(Show);
