import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const statusClass = {
    draft: 'bg-amber-100 text-amber-800',
    in_progress: 'bg-sky-100 text-sky-800',
    under_review: 'bg-violet-100 text-violet-800',
    approved: 'bg-teal-100 text-teal-800',
    posted: 'bg-emerald-100 text-emerald-800',
};

function Show({ session }) {
    const can = useCan();
    const { t } = useTranslation();
    const editable = session.status === 'in_progress';

    const initialLines = useMemo(
        () =>
            session.lines.map((line) => ({
                line_id: line.id,
                counted_qty: line.counted_qty ?? line.system_qty ?? 0,
            })),
        [session.lines],
    );

    const { data, setData, post, processing } = useForm({ lines: initialLines });

    const start = () => router.post(route('admin.count-sessions.start', session.id));
    const approve = () => router.post(route('admin.count-sessions.approve', session.id));
    const postVariances = () => router.post(route('admin.count-sessions.post', session.id));

    const submitCounts = (e) => {
        e.preventDefault();
        post(route('admin.count-sessions.submit-counts', session.id));
    };

    const updateLine = (index, countedQty) => {
        const lines = [...data.lines];
        lines[index] = { ...lines[index], counted_qty: Number(countedQty) };
        setData('lines', lines);
    };

    return (
        <>
            <Head title={session.reference_no} />
            <PageHeader title={session.reference_no} description={session.warehouse?.name}>
                <Link href={route('admin.count-sessions.index')} className="rp-btn-outline">
                    {t('pages.countSessions.backToList')}
                </Link>
                {can('inventory.cycle-count') && session.status === 'draft' && (
                    <button type="button" onClick={start} className="rp-btn-primary">
                        {t('pages.countSessions.start')}
                    </button>
                )}
                {can('inventory.cycle-count.approve') && session.status === 'under_review' && (
                    <button type="button" onClick={approve} className="rp-btn-primary">
                        {t('pages.countSessions.approve')}
                    </button>
                )}
                {can('inventory.cycle-count.approve') && session.status === 'approved' && (
                    <button type="button" onClick={postVariances} className="rp-btn-primary">
                        {t('pages.countSessions.post')}
                    </button>
                )}
            </PageHeader>

            <div className="mb-4">
                <span
                    className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${statusClass[session.status] ?? ''}`}
                >
                    {t(`pages.countSessions.status.${session.status}`)}
                </span>
                {session.blind_count && (
                    <span className="ml-2 text-xs text-rp-text-muted">{t('pages.countSessions.blindMode')}</span>
                )}
            </div>

            <form onSubmit={submitCounts}>
                <div className="overflow-x-auto rounded-xl border border-rp-border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-rp-border bg-rp-surface-inset text-left text-rp-text-muted">
                                <th className="px-4 py-3">{t('pages.inventory.columns.product')}</th>
                                <th className="px-4 py-3">{t('pages.bins.fields.binCode')}</th>
                                <th className="px-4 py-3">{t('pages.inventory.columns.systemQty')}</th>
                                <th className="px-4 py-3">{t('pages.inventory.columns.countedQty')}</th>
                                <th className="px-4 py-3">{t('pages.inventory.columns.variance')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {session.lines.map((line, index) => (
                                <tr key={line.id} className="border-b border-rp-border/50">
                                    <td className="px-4 py-3">
                                        <div className="font-medium">{line.variant?.name}</div>
                                        <div className="text-xs text-rp-text-muted">{line.variant?.sku}</div>
                                    </td>
                                    <td className="px-4 py-3 font-mono">{line.bin_code ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        {line.system_qty === null ? '—' : line.system_qty}
                                    </td>
                                    <td className="px-4 py-3">
                                        {editable ? (
                                            <input
                                                type="number"
                                                min="0"
                                                className="rp-form-input w-24"
                                                value={data.lines[index]?.counted_qty ?? 0}
                                                onChange={(e) => updateLine(index, e.target.value)}
                                            />
                                        ) : (
                                            (line.counted_qty ?? '—')
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {line.variance_qty !== null ? (
                                            <span
                                                className={
                                                    line.variance_qty !== 0
                                                        ? 'font-semibold text-amber-600'
                                                        : ''
                                                }
                                            >
                                                {line.variance_qty > 0 ? '+' : ''}
                                                {line.variance_qty}
                                            </span>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {editable && (
                    <div className="mt-4">
                        <button type="submit" disabled={processing} className="rp-btn-primary">
                            {t('pages.countSessions.submitCounts')}
                        </button>
                    </div>
                )}
            </form>
        </>
    );
}

export default withAdminLayout(Show);
