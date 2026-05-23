import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const statusClass = {
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    shipped: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
    partially_received: 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-300',
    received: 'bg-teal-100 text-teal-800 dark:bg-teal-500/20 dark:text-teal-300',
};

function Show({ transfer }) {
    const can = useCan();
    const { t } = useTranslation();
    const canReceive = transfer.status === 'shipped' || transfer.status === 'partially_received';

    const initialLines = useMemo(
        () =>
            transfer.items
                .filter((item) => item.qty_remaining > 0)
                .map((item) => ({
                    item_id: item.id,
                    quantity: item.qty_remaining,
                })),
        [transfer.items],
    );

    const { data, setData, post, processing, errors } = useForm({
        lines: initialLines,
    });

    const ship = () => {
        router.post(route('admin.stock-transfers.ship', transfer.id));
    };

    const receive = (e) => {
        e.preventDefault();
        post(route('admin.stock-transfers.receive', transfer.id));
    };

    const updateLineQty = (index, quantity) => {
        const lines = [...data.lines];
        lines[index] = { ...lines[index], quantity: Number(quantity) };
        setData('lines', lines);
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

            <form onSubmit={receive}>
                <div className="rp-card overflow-hidden">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-rp-border bg-sand-50/80 text-left text-xs font-semibold uppercase tracking-wide text-rp-text-muted dark:bg-ink-900/50">
                                <th className="px-4 py-3">{t('pages.inventory.columns.product')}</th>
                                <th className="px-4 py-3">{t('pages.inventory.columns.batch')}</th>
                                <th className="px-4 py-3 text-right">
                                    {t('pages.stockTransfers.columns.requested')}
                                </th>
                                <th className="px-4 py-3 text-right">
                                    {t('pages.stockTransfers.columns.received')}
                                </th>
                                {canReceive && can('inventory.transfer') && (
                                    <th className="px-4 py-3 text-right">
                                        {t('pages.stockTransfers.columns.receiveNow')}
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {transfer.items.map((item) => {
                                const lineIndex = data.lines.findIndex(
                                    (line) => line.item_id === item.id,
                                );

                                return (
                                    <tr
                                        key={item.id}
                                        className="border-b border-rp-border last:border-0"
                                    >
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
                                        <td className="px-4 py-3 text-right font-mono">
                                            {item.qty_requested}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            {item.qty_received}
                                        </td>
                                        {canReceive && can('inventory.transfer') && (
                                            <td className="px-4 py-3 text-right">
                                                {item.qty_remaining > 0 && lineIndex >= 0 ? (
                                                    <input
                                                        type="number"
                                                        min={1}
                                                        max={item.qty_remaining}
                                                        value={data.lines[lineIndex]?.quantity ?? ''}
                                                        className="rp-form-input w-24 text-right font-mono"
                                                        onChange={(e) =>
                                                            updateLineQty(lineIndex, e.target.value)
                                                        }
                                                    />
                                                ) : (
                                                    <span className="text-rp-text-muted">—</span>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {errors.lines && (
                    <p className="mt-3 text-sm text-red-600">{errors.lines}</p>
                )}

                {canReceive && can('inventory.transfer') && data.lines.length > 0 && (
                    <div className="mt-4 flex justify-end">
                        <button type="submit" disabled={processing} className="rp-btn-primary">
                            {t('pages.stockTransfers.receive')}
                        </button>
                    </div>
                )}
            </form>

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
