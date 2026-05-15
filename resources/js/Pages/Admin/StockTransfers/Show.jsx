import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

const statusClass = {
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    shipped: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
    received: 'bg-teal-100 text-teal-800 dark:bg-teal-500/20 dark:text-teal-300',
};

function Show({ transfer }) {
    const can = useCan();
    const { t } = useTranslation();

    const ship = () => {
        router.post(route('admin.stock-transfers.ship', transfer.id));
    };

    const receive = () => {
        router.post(route('admin.stock-transfers.receive', transfer.id));
    };

    return (
        <>
            <Head title={transfer.reference_no} />
            <PageHeader title={transfer.reference_no} description={transfer.from_warehouse.name}>
                <div className="flex flex-wrap gap-2">
                    <Link href={route('admin.stock-transfers.index')} className="rp-btn-outline">
                        {t('pages.stockTransfers.backToList')}
                    </Link>
                    {can('inventory.transfer') && transfer.status === 'draft' && (
                        <button type="button" onClick={ship} className="rp-btn-primary">
                            {t('pages.stockTransfers.ship')}
                        </button>
                    )}
                    {can('inventory.transfer') && transfer.status === 'shipped' && (
                        <button type="button" onClick={receive} className="rp-btn-primary">
                            {t('pages.stockTransfers.receive')}
                        </button>
                    )}
                </div>
            </PageHeader>

            <div className="mb-6 flex flex-wrap items-center gap-3">
                <span
                    className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusClass[transfer.status] ?? ''}`}
                >
                    {t(`pages.stockTransfers.status.${transfer.status}`)}
                </span>
                <span className="text-sm text-rp-text-muted">
                    {transfer.from_warehouse.name} → {transfer.to_warehouse.name}
                </span>
            </div>

            {transfer.notes && (
                <p className="mb-6 text-sm text-rp-text-secondary">{transfer.notes}</p>
            )}

            <div className="rp-card overflow-hidden">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-rp-border bg-sand-50/80 text-left text-xs font-semibold uppercase tracking-wide text-rp-text-muted dark:bg-ink-900/50">
                            <th className="px-4 py-3">{t('pages.inventory.columns.product')}</th>
                            <th className="px-4 py-3">{t('pages.inventory.columns.batch')}</th>
                            <th className="px-4 py-3 text-right">
                                {t('pages.inventory.fields.quantity')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {transfer.items.map((item) => (
                            <tr key={item.id} className="border-b border-rp-border last:border-0">
                                <td className="px-4 py-3">
                                    <div className="font-medium text-rp-text">
                                        {item.variant.product_name}
                                    </div>
                                    <div className="text-xs text-rp-text-muted">
                                        {item.variant.name} · {item.variant.sku}
                                    </div>
                                </td>
                                <td className="px-4 py-3 text-rp-text-secondary">
                                    {item.batch?.batch_no ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-right font-mono">{item.quantity}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <dl className="mt-6 grid gap-4 text-sm sm:grid-cols-3">
                {transfer.creator && (
                    <div>
                        <dt className="text-rp-text-muted">{t('pages.stockTransfers.createdBy')}</dt>
                        <dd className="font-medium text-rp-text">{transfer.creator.name}</dd>
                    </div>
                )}
                {transfer.shipper && (
                    <div>
                        <dt className="text-rp-text-muted">{t('pages.stockTransfers.shippedBy')}</dt>
                        <dd className="font-medium text-rp-text">{transfer.shipper.name}</dd>
                    </div>
                )}
                {transfer.receiver && (
                    <div>
                        <dt className="text-rp-text-muted">{t('pages.stockTransfers.receivedBy')}</dt>
                        <dd className="font-medium text-rp-text">{transfer.receiver.name}</dd>
                    </div>
                )}
            </dl>
        </>
    );
}

export default withAdminLayout(Show);
