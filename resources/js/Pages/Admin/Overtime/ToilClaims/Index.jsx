import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Coins, Plus, Search } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ claims, filters }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.overtime.toil-claims.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const approve = (id) => {
        router.post(route('admin.overtime.toil-claims.approve', id));
    };

    const reject = (id) => {
        router.post(route('admin.overtime.toil-claims.reject', id));
    };

    const cancel = (id) => {
        router.post(route('admin.overtime.toil-claims.cancel', id));
    };

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.toilClaims.allStatuses') },
            ...['pending', 'approved', 'rejected', 'cancelled'].map((status) => ({
                value: status,
                label: t(`pages.toilClaims.statuses.${status}`),
            })),
        ],
        [t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'employee',
                header: t('pages.toilClaims.columns.employee'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300">
                            <Coins className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text-primary">
                                {row.original.employee ?? '—'}
                            </div>
                            <div className="text-xs text-rp-text-muted">{row.original.employee_code ?? '—'}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'hours',
                header: t('pages.toilClaims.columns.hours'),
                cell: ({ row }) => row.original.hours ?? '—',
            },
            {
                id: 'createdAt',
                header: t('pages.toilClaims.columns.createdAt'),
                cell: ({ row }) => row.original.created_at ?? '—',
            },
            {
                id: 'status',
                header: t('pages.toilClaims.columns.status'),
                cell: ({ row }) =>
                    t(`pages.toilClaims.statuses.${row.original.status}`, { defaultValue: row.original.status }),
            },
            {
                id: 'actions',
                header: t('common.actions'),
                cell: ({ row }) => {
                    if (row.original.status === 'pending' && can('toil.approve-cash-claim')) {
                        return (
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onClick={() => approve(row.original.id)}
                                    className="rp-btn-outline text-sm"
                                >
                                    {t('pages.toilClaims.approve')}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => reject(row.original.id)}
                                    className="rp-btn-outline text-sm"
                                >
                                    {t('pages.toilClaims.reject')}
                                </button>
                            </div>
                        );
                    }
                    if (row.original.status === 'pending' && can('toil.request-cash-claim')) {
                        return (
                            <button
                                type="button"
                                onClick={() => cancel(row.original.id)}
                                className="rp-btn-outline text-sm"
                            >
                                {t('pages.toilClaims.cancel')}
                            </button>
                        );
                    }
                    return '—';
                },
            },
        ],
        [can, t],
    );

    return (
        <>
            <Head title={t('pages.toilClaims.indexTitle')} />
            <PageHeader title={t('pages.toilClaims.indexTitle')} description={t('pages.toilClaims.indexDescription')}>
                {can('toil.request-cash-claim') && (
                    <Button variant="brand" asChild>
                        <Link
                            href={route('admin.overtime.toil-claims.create')}
                            className="inline-flex items-center gap-2"
                        >
                            <Plus className="h-4 w-4" />
                            {t('pages.toilClaims.createTitle')}
                        </Link>
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.toilClaims.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={statusOptions}
                />
                <Button type="submit" variant="outline">
                    {t('common.search')}
                </Button>
            </form>

            <DataTable
                columns={columns}
                data={claims.data ?? []}
                pagination={claims}
                emptyMessage={t('pages.toilClaims.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
