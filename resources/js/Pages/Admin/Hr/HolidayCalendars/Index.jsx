import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyForm() {
    return {
        code: '',
        name: '',
        legal_entity_id: '',
        branch_id: '',
        status: 'active',
    };
}

function Index({ calendars, filters, legalEntities = [], branches = [] }) {
    const can = useCan();
    const { t } = useTranslation();
    const [modalOpen, setModalOpen] = useState(false);
    const form = useForm(emptyForm());

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('common.allStatuses') },
            { value: 'active', label: t('pages.holidayCalendars.statuses.active') },
            { value: 'inactive', label: t('pages.holidayCalendars.statuses.inactive') },
        ],
        [t],
    );

    const entityOptions = useMemo(
        () => [
            { value: '', label: t('pages.hrEmployees.selectLegalEntity') },
            ...legalEntities.map((e) => ({ value: String(e.id), label: e.legal_name })),
        ],
        [legalEntities, t],
    );

    const branchOptions = useMemo(
        () => [
            { value: '', label: t('pages.holidayCalendars.fields.noBranch') },
            ...branches.map((b) => ({ value: String(b.id), label: b.name })),
        ],
        [branches, t],
    );

    const formStatusOptions = useMemo(
        () => [
            { value: 'active', label: t('pages.holidayCalendars.statuses.active') },
            { value: 'inactive', label: t('pages.holidayCalendars.statuses.inactive') },
        ],
        [t],
    );

    const openCreate = () => {
        form.clearErrors();
        form.setData(emptyForm());
        setModalOpen(true);
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('admin.hr.holiday-calendars.store'), {
            onSuccess: () => setModalOpen(false),
        });
    };

    const search = (e) => {
        e.preventDefault();
        router.get(route('admin.hr.holiday-calendars.index'), Object.fromEntries(new FormData(e.target)), {
            preserveState: true,
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'name',
                header: t('pages.holidayCalendars.columns.name'),
                cell: ({ row }) => (
                    <Link
                        href={route('admin.hr.holiday-calendars.show', row.original.id)}
                        className="flex items-center gap-3 hover:underline"
                    >
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                            <CalendarDays className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-teal-600">{row.original.name}</div>
                            <div className="text-xs text-rp-text-muted">{row.original.code}</div>
                        </div>
                    </Link>
                ),
            },
            {
                id: 'entity',
                header: t('pages.holidayCalendars.columns.legalEntity'),
                cell: ({ row }) => row.original.legal_entity_name ?? '—',
            },
            {
                id: 'branch',
                header: t('pages.holidayCalendars.columns.branch'),
                cell: ({ row }) => row.original.branch_name ?? '—',
            },
            {
                id: 'status',
                header: t('pages.holidayCalendars.columns.status'),
                cell: ({ row }) =>
                    t(`pages.holidayCalendars.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
        ],
        [t],
    );

    const rowActions = (row) => [
        {
            label: t('common.view'),
            type: 'view',
            href: route('admin.hr.holiday-calendars.show', row.id),
        },
    ];

    return (
        <>
            <Head title={t('pages.holidayCalendars.indexTitle')} />
            <PageHeader
                title={t('pages.holidayCalendars.indexTitle')}
                description={t('pages.holidayCalendars.indexDescription')}
            >
                {can('holiday.manage') && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.holidayCalendars.createTitle')}
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.holidayCalendars.searchPlaceholder')}
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
                data={calendars.data ?? []}
                pagination={calendars}
                rowActions={rowActions}
                emptyMessage={t('pages.holidayCalendars.empty')}
            />

            <Modal show={modalOpen} onClose={() => setModalOpen(false)} maxWidth="lg">
                <form onSubmit={submit} className="space-y-4 p-6">
                    <h3 className="text-lg font-semibold">{t('pages.holidayCalendars.createTitle')}</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField label={t('pages.holidayCalendars.fields.code')} id="code" error={form.errors.code}>
                            <input
                                id="code"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value)}
                                className="rp-form-input"
                                required
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.holidayCalendars.fields.name')} id="name" error={form.errors.name}>
                            <input
                                id="name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                className="rp-form-input"
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.hrEmployees.fields.legalEntity')}
                            id="legal_entity_id"
                            error={form.errors.legal_entity_id}
                        >
                            <Select
                                id="legal_entity_id"
                                value={form.data.legal_entity_id}
                                onChange={(value) => form.setData('legal_entity_id', value ?? '')}
                                options={entityOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.holidayCalendars.columns.branch')}
                            id="branch_id"
                            error={form.errors.branch_id}
                        >
                            <Select
                                id="branch_id"
                                value={form.data.branch_id}
                                onChange={(value) => form.setData('branch_id', value ?? '')}
                                options={branchOptions}
                                isClearable
                            />
                        </AdminFormField>
                        <AdminFormField label={t('common.status')} id="status" error={form.errors.status}>
                            <Select
                                id="status"
                                value={form.data.status}
                                onChange={(value) => form.setData('status', value ?? 'active')}
                                options={formStatusOptions}
                            />
                        </AdminFormField>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            {t('confirm.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {t('pages.holidayCalendars.createSubmit')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
