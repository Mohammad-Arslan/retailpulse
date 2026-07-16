import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyRunForm(entities = []) {
    return {
        legal_entity_id: entities[0] ? String(entities[0].id) : '',
        branch_id: '',
        period_start: '',
        period_end: '',
        currency_code: entities[0]?.currency_code ?? '',
    };
}

function Index({ runs, entities, branches = [], filters }) {
    const { t } = useTranslation();
    const can = useCan();
    const canProcess = can('payroll.process');
    const canApprove = can('payroll.approve');
    const canPost = can('payroll.post');
    const canReverse = can('payroll.reverse');
    const canEmailPayslips = can('payroll.process');
    const canCreate = can('payroll.process');

    const [modalOpen, setModalOpen] = useState(false);
    const form = useForm(emptyRunForm(entities));

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.payroll.runs.index'), Object.fromEntries(formData), { preserveState: true });
    };

    const entityOptions = useMemo(
        () => [
            { value: '', label: t('pages.payrollRuns.allEntities') },
            ...(entities ?? []).map((entity) => ({ value: String(entity.id), label: entity.name })),
        ],
        [entities, t],
    );

    const branchOptions = useMemo(
        () => [
            { value: '', label: t('pages.payrollRuns.noBranch') },
            ...(branches ?? []).map((branch) => ({ value: String(branch.id), label: branch.name })),
        ],
        [branches, t],
    );

    const openCreate = () => {
        form.clearErrors();
        form.setData(emptyRunForm(entities));
        setModalOpen(true);
    };

    const onEntityChange = (value) => {
        const entity = (entities ?? []).find((e) => String(e.id) === value);
        form.setData((data) => ({
            ...data,
            legal_entity_id: value ?? '',
            currency_code: entity?.currency_code ?? data.currency_code,
        }));
    };

    const submitCreate = (e) => {
        e.preventDefault();
        form.post(route('admin.payroll.runs.store'), {
            preserveScroll: true,
            onSuccess: () => setModalOpen(false),
        });
    };

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.payrollRuns.allStatuses') },
            ...['draft', 'pending_approval', 'approved', 'posted', 'reversed'].map((status) => ({
                value: status,
                label: t(`pages.payrollRuns.statuses.${status}`, { defaultValue: status }),
            })),
        ],
        [t],
    );

    const action = (routeName, id) => {
        router.post(route(routeName, { payroll_run: id }), {}, { preserveState: false });
    };

    const columns = useMemo(
        () => [
            {
                id: 'payrollNumber',
                header: t('pages.payrollRuns.columns.payrollNumber'),
                cell: ({ row }) => (
                    <Link
                        href={route('admin.payroll.runs.show', row.original.id)}
                        className="text-sm font-semibold text-teal-600 hover:underline"
                    >
                        {row.original.payroll_number ?? t('pages.payrollRuns.draft')}
                    </Link>
                ),
            },
            {
                id: 'legalEntity',
                header: t('pages.payrollRuns.columns.legalEntity'),
                cell: ({ row }) => row.original.legal_entity ?? '—',
            },
            {
                id: 'period',
                header: t('pages.payrollRuns.columns.period'),
                cell: ({ row }) => `${row.original.period_start} → ${row.original.period_end}`,
            },
            {
                id: 'totals',
                header: t('pages.payrollRuns.columns.totals'),
                cell: ({ row }) =>
                    row.original.totals != null
                        ? `${t('pages.payrollRuns.totalNet')}: ${Number(row.original.totals.total_net ?? 0).toLocaleString()}`
                        : '—',
            },
            {
                id: 'status',
                header: t('pages.payrollRuns.columns.status'),
                cell: ({ row }) =>
                    t(`pages.payrollRuns.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
            {
                id: 'actions',
                header: '',
                cell: ({ row }) => {
                    const s = row.original.status;
                    return (
                        <div className="flex flex-wrap gap-1">
                            {canProcess && s === 'draft' && (
                                <button
                                    type="button"
                                    className="rp-btn-outline text-xs"
                                    onClick={() => action('admin.payroll.runs.calculate', row.original.id)}
                                >
                                    {t('pages.payrollRuns.calculate')}
                                </button>
                            )}
                            {canProcess && s === 'draft' && (
                                <button
                                    type="button"
                                    className="rp-btn-outline text-xs"
                                    onClick={() => action('admin.payroll.runs.submit', row.original.id)}
                                >
                                    {t('pages.payrollRuns.submit')}
                                </button>
                            )}
                            {canApprove && ['draft', 'pending_approval'].includes(s) && (
                                <button
                                    type="button"
                                    className="rp-btn-outline text-xs"
                                    onClick={() => action('admin.payroll.runs.approve', row.original.id)}
                                >
                                    {t('pages.payrollRuns.approve')}
                                </button>
                            )}
                            {canPost && s === 'approved' && (
                                <button
                                    type="button"
                                    className="rp-btn-primary text-xs"
                                    onClick={() => action('admin.payroll.runs.post', row.original.id)}
                                >
                                    {t('pages.payrollRuns.post')}
                                </button>
                            )}
                            {canReverse && s === 'posted' && (
                                <button
                                    type="button"
                                    className="rp-btn-outline text-xs"
                                    onClick={() => action('admin.payroll.runs.reverse', row.original.id)}
                                >
                                    {t('pages.payrollRuns.reverse')}
                                </button>
                            )}
                            {canEmailPayslips && ['approved', 'posted'].includes(s) && (
                                <button
                                    type="button"
                                    className="rp-btn-outline text-xs"
                                    onClick={() => action('admin.payroll.runs.payslips.email', row.original.id)}
                                >
                                    {t('pages.payrollRuns.emailPayslips')}
                                </button>
                            )}
                        </div>
                    );
                },
            },
        ],
        [t, canProcess, canApprove, canPost, canReverse, canEmailPayslips],
    );

    return (
        <>
            <Head title={t('pages.payrollRuns.indexTitle')} />
            <PageHeader
                title={t('pages.payrollRuns.indexTitle')}
                description={t('pages.payrollRuns.indexDescription')}
            >
                {canCreate && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.payrollRuns.newRun')}
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <Select
                    name="legal_entity_id"
                    defaultValue={filters.legal_entity_id ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={entityOptions}
                />
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

            <DataTable columns={columns} data={runs.data ?? []} pagination={runs} />

            <Modal show={modalOpen} onClose={() => setModalOpen(false)} maxWidth="lg">
                <form onSubmit={submitCreate} className="space-y-4 p-6">
                    <h3 className="text-lg font-semibold">{t('pages.payrollRuns.newRun')}</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.payrollRuns.columns.legalEntity')}
                            id="legal_entity_id"
                            error={form.errors.legal_entity_id}
                        >
                            <Select
                                id="legal_entity_id"
                                value={form.data.legal_entity_id}
                                onChange={onEntityChange}
                                options={entityOptions.filter((o) => o.value !== '')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.payrollRuns.fields.branch')}
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
                        <AdminFormField
                            label={t('pages.payrollRuns.fields.periodStart')}
                            id="period_start"
                            error={form.errors.period_start}
                            required
                        >
                            <input
                                type="date"
                                id="period_start"
                                className="rp-form-input"
                                value={form.data.period_start}
                                onChange={(e) => form.setData('period_start', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.payrollRuns.fields.periodEnd')}
                            id="period_end"
                            error={form.errors.period_end}
                            required
                        >
                            <input
                                type="date"
                                id="period_end"
                                className="rp-form-input"
                                value={form.data.period_end}
                                onChange={(e) => form.setData('period_end', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.payrollRuns.fields.currencyCode')}
                            id="currency_code"
                            error={form.errors.currency_code}
                            required
                        >
                            <input
                                id="currency_code"
                                className="rp-form-input font-mono uppercase"
                                maxLength={3}
                                value={form.data.currency_code}
                                onChange={(e) => form.setData('currency_code', e.target.value.toUpperCase())}
                                required
                            />
                        </AdminFormField>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            {t('confirm.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {t('pages.payrollRuns.createSubmit')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
