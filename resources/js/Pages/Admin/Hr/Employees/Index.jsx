import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import ImportExportToolbar from '@/Components/import-export/ImportExportToolbar';
import { useImportJobsTray } from '@/Components/import-export/ImportJobsTray';
import { Button } from '@/Components/ui/button';
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
    const { trackJob } = useImportJobsTray();

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.hr.employees.index'), Object.fromEntries(form), { preserveState: true });
    };

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('common.allStatuses') },
            { value: 'active', label: t('pages.hrEmployees.statuses.active') },
            { value: 'inactive', label: t('pages.hrEmployees.statuses.inactive') },
            { value: 'terminated', label: t('pages.hrEmployees.statuses.terminated') },
        ],
        [t],
    );

    const branchOptions = useMemo(
        () => [
            { value: '', label: t('pages.hrEmployees.allBranches') },
            ...branches.map((b) => ({ value: String(b.id), label: b.name })),
        ],
        [branches, t],
    );

    const exportFilters = {
        search: filters.search ?? undefined,
        status: filters.status ?? undefined,
        branch_id: filters.branch_id ?? undefined,
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

    const rowActions = (row) => {
        const actions = [
            {
                label: t('common.view'),
                type: 'view',
                onClick: () => router.visit(route('admin.hr.employees.show', row.id)),
            },
        ];

        if (can('hr.manage-employees')) {
            actions.push({
                label: t('common.edit'),
                type: 'edit',
                onClick: () => router.visit(route('admin.hr.employees.edit', row.id)),
            });
        }

        return actions;
    };

    return (
        <>
            <Head title={t('pages.hrEmployees.indexTitle')} />
            <PageHeader
                title={t('pages.hrEmployees.indexTitle')}
                description={t('pages.hrEmployees.indexDescription')}
            >
                <div className="flex flex-wrap items-center gap-2">
                    <ImportExportToolbar
                        entityType="employees"
                        entityLabel={t('pages.hrEmployees.indexTitle')}
                        exportOptions={{ filters: exportFilters }}
                        onJobStarted={trackJob}
                    />
                    {can('hr.manage-employees') && (
                        <Button variant="brand" asChild>
                            <Link href={route('admin.hr.employees.create')} className="inline-flex items-center gap-2">
                                <Plus className="h-4 w-4" />
                                {t('pages.hrEmployees.createTitle')}
                            </Link>
                        </Button>
                    )}
                </div>
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.hrEmployees.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={statusOptions}
                />
                <Select
                    name="branch_id"
                    defaultValue={filters.branch_id ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={branchOptions}
                />
                <Button type="submit" variant="outline">
                    {t('common.search')}
                </Button>
            </form>

            <DataTable
                columns={columns}
                data={employees.data ?? []}
                pagination={employees}
                rowActions={rowActions}
                emptyMessage={t('pages.hrEmployees.empty')}
            />
        </>
    );
}

export default withAdminLayout(Index);
