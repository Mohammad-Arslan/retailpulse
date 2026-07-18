import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import ModalHeader from '@/Components/common/ModalHeader';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarClock, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ entitlements, filters, leaveTypes = [] }) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);
    const form = useForm({ accrued_days: '', carried_forward_days: '' });

    const leaveTypeOptions = useMemo(
        () => [
            { value: '', label: t('pages.leaveEntitlements.allLeaveTypes') },
            ...leaveTypes.map((type) => ({ value: String(type.id), label: `${type.name} (${type.code})` })),
        ],
        [leaveTypes, t],
    );

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.leave.entitlements.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const openAdjust = (row) => {
        setEditing(row);
        form.clearErrors();
        form.setData({
            accrued_days: String(row.accrued_days),
            carried_forward_days: String(row.carried_forward_days),
        });
        setModalOpen(true);
    };

    const submit = (e) => {
        e.preventDefault();
        form.put(route('admin.leave.entitlements.update', editing.id), {
            preserveScroll: true,
            onSuccess: () => setModalOpen(false),
        });
    };

    const columns = useMemo(
        () => [
            {
                id: 'employee',
                header: t('pages.leaveEntitlements.columns.employee'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <CalendarClock className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">{row.original.employee ?? '—'}</div>
                            <div className="font-mono text-xs text-rp-text-muted">{row.original.employee_code ?? '—'}</div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'leaveType',
                header: t('pages.leaveEntitlements.columns.leaveType'),
                cell: ({ row }) => (
                    <div>
                        <div className="text-sm text-rp-text">{row.original.leave_type ?? '—'}</div>
                        <div className="font-mono text-xs text-rp-text-muted">{row.original.leave_type_code ?? '—'}</div>
                    </div>
                ),
            },
            {
                id: 'accrued',
                header: t('pages.leaveEntitlements.columns.accrued'),
                cell: ({ row }) => row.original.accrued_days.toFixed(2),
            },
            {
                id: 'used',
                header: t('pages.leaveEntitlements.columns.used'),
                cell: ({ row }) => row.original.used_days.toFixed(2),
            },
            {
                id: 'carriedForward',
                header: t('pages.leaveEntitlements.columns.carriedForward'),
                cell: ({ row }) => row.original.carried_forward_days.toFixed(2),
            },
            {
                id: 'encashed',
                header: t('pages.leaveEntitlements.columns.encashed'),
                cell: ({ row }) => row.original.encashed_days.toFixed(2),
            },
            {
                id: 'remaining',
                header: t('pages.leaveEntitlements.columns.remaining'),
                cell: ({ row }) => (
                    <span className="font-semibold text-rp-text">{row.original.remaining_days.toFixed(2)}</span>
                ),
            },
            {
                id: 'lastAccrual',
                header: t('pages.leaveEntitlements.columns.lastAccrual'),
                cell: ({ row }) => row.original.accrual_last_run_on ?? '—',
            },
        ],
        [t],
    );

    const rowActions = (row) => {
        if (!can('leave.manage-entitlements')) {
            return [];
        }

        return [{ label: t('pages.leaveEntitlements.adjust'), type: 'edit', onClick: () => openAdjust(row) }];
    };

    return (
        <>
            <Head title={t('pages.leaveEntitlements.indexTitle')} />
            <PageHeader
                title={t('pages.leaveEntitlements.indexTitle')}
                description={t('pages.leaveEntitlements.indexDescription')}
            />

            <form onSubmit={search} className="rp-filter-bar mb-4 flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.leaveEntitlements.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="leave_type_id"
                    defaultValue={filters.leave_type_id ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={leaveTypeOptions}
                />
                <Button type="submit" variant="outline">
                    {t('common.search')}
                </Button>
            </form>

            <DataTable
                columns={columns}
                data={entitlements.data ?? []}
                pagination={entitlements}
                rowActions={rowActions}
                emptyMessage={t('pages.leaveEntitlements.empty')}
            />

            <Modal show={modalOpen} onClose={() => setModalOpen(false)} maxWidth="md">
                <ModalHeader
                    icon={CalendarClock}
                    title={t('pages.leaveEntitlements.adjustTitle')}
                    description={editing ? `${editing.employee} — ${editing.leave_type}` : undefined}
                    onClose={() => setModalOpen(false)}
                />
                <form onSubmit={submit} className="space-y-4 p-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.leaveEntitlements.fields.accruedDays')}
                            error={form.errors.accrued_days}
                            required
                        >
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                className="rp-form-input"
                                value={form.data.accrued_days}
                                onChange={(e) => form.setData('accrued_days', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.leaveEntitlements.fields.carriedForwardDays')}
                            error={form.errors.carried_forward_days}
                            required
                        >
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                className="rp-form-input"
                                value={form.data.carried_forward_days}
                                onChange={(e) => form.setData('carried_forward_days', e.target.value)}
                                required
                            />
                        </AdminFormField>
                    </div>
                    <p className="text-xs text-rp-text-muted">{t('pages.leaveEntitlements.adjustHint')}</p>
                    <div className="flex justify-end gap-2 border-t border-rp-border pt-4">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            {t('confirm.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {t('common.save')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
