import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link } from '@inertiajs/react';
import { Gift, Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ programs }) {
    const can = useCan();
    const { t } = useTranslation();

    const columns = useMemo(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: t('pages.loyalty.programs.columns.name'),
                cell: ({ row }) => (
                    <Link
                        href={route('admin.loyalty.programs.show', row.original.id)}
                        className="flex items-center gap-3 font-semibold text-teal-600 hover:underline"
                    >
                        <Gift className="h-4 w-4" />
                        {row.original.name}
                    </Link>
                ),
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: t('pages.loyalty.programs.columns.status'),
                cell: ({ row }) => <span className="capitalize">{row.original.status?.replace('_', ' ')}</span>,
            },
            {
                id: 'scope_type',
                accessorKey: 'scope_type',
                header: t('pages.loyalty.programs.columns.scope'),
                cell: ({ row }) => <span className="capitalize">{row.original.scope_type?.replace('_', ' ')}</span>,
            },
            {
                id: 'rules_count',
                accessorKey: 'rules_count',
                header: t('pages.loyalty.programs.columns.rules'),
            },
            {
                id: 'wallets_count',
                accessorKey: 'wallets_count',
                header: t('pages.loyalty.programs.columns.members'),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.loyalty.programs.title')} />
            <PageHeader title={t('pages.loyalty.programs.title')} description={t('pages.loyalty.programs.description')}>
                {can('loyalty.manage-programs') && (
                    <Link href={route('admin.loyalty.programs.create')} className="rp-btn-primary">
                        <Plus className="h-4 w-4" />
                        {t('pages.loyalty.programs.createTitle')}
                    </Link>
                )}
            </PageHeader>
            <DataTable columns={columns} data={programs.data ?? []} emptyMessage={t('pages.loyalty.programs.empty')} />
        </>
    );
}

export default withAdminLayout(Index);
