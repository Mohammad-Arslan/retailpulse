import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

function Index({ transactions }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('pages.loyalty.transactions.title')} />
            <PageHeader title={t('pages.loyalty.transactions.title')} description={t('pages.loyalty.transactions.description')} />
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b bg-muted/30 text-left text-muted-foreground">
                            <th className="px-3 py-2">Customer</th>
                            <th className="px-3 py-2">Program</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Points</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        {transactions.map((tx) => (
                            <tr key={tx.id} className="border-b">
                                <td className="px-3 py-2">{tx.customer}</td>
                                <td className="px-3 py-2">{tx.program}</td>
                                <td className="px-3 py-2 capitalize">{tx.transaction_type?.replace('_', ' ')}</td>
                                <td className="px-3 py-2">{tx.points}</td>
                                <td className="px-3 py-2 capitalize">{tx.status?.replace('_', ' ')}</td>
                                <td className="px-3 py-2">{tx.created_at ? new Date(tx.created_at).toLocaleString() : '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}

export default withAdminLayout(Index);
