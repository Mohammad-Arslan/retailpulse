import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, Users } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ employees, filters, branches = [] }) {
    const can = useCan();
    const { t } = useTranslation();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.hr.employees.index'), Object.fromEntries(form), { preserveState: true });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: t('pages.hrEmployees.columns.employee'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <Users className="h-4 w-4" />
                        </span>
                        <div>
                            <Link
                                href={route('admin.hr.employees.show', row.original.id)}
                                className="text-sm font-semibold text-teal-600 hover:underline"
                            >
                                {row.original.name}
                            </Link>
                            <div className="text-xs text-rp-text-muted">{row.original.employee_code}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'branch',
                header: t('pages.hrEmployees.columns.branch'),
                cell: ({ row }) => row.original.branch ?? '—',
            },
            {
                id: 'employment_type',
                header: t('pages.hrEmployees.columns.type'),
                cell: ({ row }) =>
                    t(`pages.hrEmployees.employmentTypes.${row.original.employment_type}`, {
                        defaultValue: row.original.employment_type,
                    }),
            },
            {
                id: 'status',
                header: t('pages.hrEmployees.columns.status'),
                cell: ({ row }) => (
                    <span
                        className={`text-xs font-medium ${
                            row.original.status === 'active' ? 'text-teal-600' : 'text-rp-text-muted'
                        }`}
                    >
                        {t(`pages.hrEmployees.statuses.${row.original.status}`, {
                            defaultValue: row.original.status,
                        })}
                    </span>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('pages.hrEmployees.indexTitle')} />
            <PageHeader
                title={t('pages.hrEmployees.indexTitle')}
                description={t('pages.hrEmployees.indexDescription')}
            >
                {can('hr.manage-employees') && (
                    <Link href={route('admin.hr.employees.create')} className="rp-btn-primary inline-flex items-center gap-2">
                        <Plus className="h-4 w-4" />
                        {t('pages.hrEmployees.createTitle')}
                    </Link>
                )}
            </PageHeader>

            <form onSubmit={search} className="mb-4 flex flex-wrap gap-2">
                <div className="relative min-w-[220px] flex-1">
                    <Search className="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.hrEmployees.searchPlaceholder')}
                        className="rp-input w-full pl-9"
                    />
                </div>
                <Select name="status" defaultValue={filters.status ?? ''} className="w-40">
                    <option value="">{t('common.all')}</option>
                    <option value="active">{t('pages.hrEmployees.statuses.active')}</option>
                    <option value="inactive">{t('pages.hrEmployees.statuses.inactive')}</option>
                    <option value="terminated">{t('pages.hrEmployees.statuses.terminated')}</option>
                </Select>
                <Select name="branch_id" defaultValue={filters.branch_id ?? ''} className="w-48">
                    <option value="">{t('pages.hrEmployees.allBranches')}</option>
                    {branches.map((b) => (
                        <option key={b.id} value={b.id}>
                            {b.name}
                        </option>
                    ))}
                </Select>
                <button type="submit" className="rp-btn-outline">
                    {t('common.apply')}
                </button>
            </form>

            <DataTable columns={columns} data={employees.data ?? []} pagination={employees} />
        </>
    );
}

export default withAdminLayout(Index);
