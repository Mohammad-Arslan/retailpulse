import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarRange, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Index({ requests, filters }) {
    const can = useCan();
    const { t } = useTranslation();
    const [rescheduling, setRescheduling] = useState(null);
    const rescheduleForm = useForm({ new_start_date: '', new_end_date: '', reason: '' });

    const search = (e) => {
        e.preventDefault();
        const form = new FormData(e.target);
        router.get(route('admin.leave.requests.index'), Object.fromEntries(form), {
            preserveState: true,
        });
    };

    const approve = (id) => {
        router.post(route('admin.leave.requests.approve', id));
    };

    const reject = (id) => {
        router.post(route('admin.leave.requests.reject', id));
    };

    const openReschedule = (row) => {
        setRescheduling(row);
        rescheduleForm.clearErrors();
        rescheduleForm.setData({
            new_start_date: row.start_date ?? '',
            new_end_date: row.end_date ?? '',
            reason: '',
        });
    };

    const submitReschedule = (e) => {
        e.preventDefault();
        rescheduleForm.post(route('admin.leave.requests.reschedule', rescheduling.id), {
            preserveScroll: true,
            onSuccess: () => setRescheduling(null),
        });
    };

    const statusOptions = useMemo(
        () => [
            { value: '', label: t('pages.leaveRequests.allStatuses') },
            ...['pending', 'approved', 'rejected', 'cancelled'].map((status) => ({
                value: status,
                label: t(`pages.leaveRequests.statuses.${status}`),
            })),
        ],
        [t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'employee',
                header: t('pages.leaveRequests.columns.employee'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-500/20 dark:text-violet-300">
                            <CalendarRange className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text-primary">
                                {row.original.employee ?? '—'}
                            </div>
                            <div className="text-xs text-rp-text-muted">
                                {row.original.employee_code ?? '—'}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'leaveType',
                header: t('pages.leaveRequests.columns.leaveType'),
                cell: ({ row }) => row.original.leave_type ?? '—',
            },
            {
                id: 'dates',
                header: t('pages.leaveRequests.columns.dates'),
                cell: ({ row }) => `${row.original.start_date ?? '—'} → ${row.original.end_date ?? '—'}`,
            },
            {
                id: 'duration',
                header: t('pages.leaveRequests.columns.duration'),
                cell: ({ row }) => {
                    const type = t(`pages.leaveRequests.durationTypes.${row.original.duration_type}`, {
                        defaultValue: row.original.duration_type,
                    });
                    if (row.original.duration_type === 'half_day' && row.original.session) {
                        const session = t(`pages.leaveRequests.sessions.${row.original.session}`, {
                            defaultValue: row.original.session,
                        });
                        return `${type} (${session})`;
                    }
                    if (row.original.duration_type === 'short_leave' && row.original.start_time) {
                        return `${type} (${row.original.start_time}–${row.original.end_time})`;
                    }
                    return type;
                },
            },
            {
                id: 'days',
                header: t('pages.leaveRequests.columns.days'),
                cell: ({ row }) => row.original.days ?? '—',
            },
            {
                id: 'status',
                header: t('pages.leaveRequests.columns.status'),
                cell: ({ row }) =>
                    t(`pages.leaveRequests.statuses.${row.original.status}`, {
                        defaultValue: row.original.status,
                    }),
            },
            {
                id: 'actions',
                header: t('common.actions'),
                cell: ({ row }) =>
                    row.original.status === 'pending' && can('leave.approve') ? (
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => approve(row.original.id)}
                                className="rp-btn-outline text-sm"
                            >
                                {t('pages.leaveRequests.approve')}
                            </button>
                            <button
                                type="button"
                                onClick={() => reject(row.original.id)}
                                className="rp-btn-outline text-sm"
                            >
                                {t('pages.leaveRequests.reject')}
                            </button>
                            {row.original.leave_type_code === 'TOIL' && (
                                <button
                                    type="button"
                                    onClick={() => openReschedule(row.original)}
                                    className="rp-btn-outline text-sm"
                                >
                                    {t('pages.leaveRequests.reschedule')}
                                    {row.original.reschedule_count > 0 && ` (${row.original.reschedule_count})`}
                                </button>
                            )}
                        </div>
                    ) : (
                        '—'
                    ),
            },
        ],
        [can, t],
    );

    return (
        <>
            <Head title={t('pages.leaveRequests.indexTitle')} />
            <PageHeader
                title={t('pages.leaveRequests.indexTitle')}
                description={t('pages.leaveRequests.indexDescription')}
            >
                {can('leave.request') && (
                    <Button variant="brand" asChild>
                        <Link href={route('admin.leave.requests.create')} className="inline-flex items-center gap-2">
                            <Plus className="h-4 w-4" />
                            {t('pages.leaveRequests.createTitle')}
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
                        placeholder={t('pages.leaveRequests.searchPlaceholder')}
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
                data={requests.data ?? []}
                pagination={requests}
                emptyMessage={t('pages.leaveRequests.empty')}
            />

            <Modal show={rescheduling !== null} onClose={() => setRescheduling(null)} maxWidth="md">
                <form onSubmit={submitReschedule} className="space-y-4 p-6">
                    <h3 className="text-lg font-semibold">{t('pages.leaveRequests.rescheduleTitle')}</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.leaveRequests.fields.startDate')}
                            id="new_start_date"
                            error={rescheduleForm.errors.new_start_date}
                        >
                            <input
                                id="new_start_date"
                                type="date"
                                value={rescheduleForm.data.new_start_date}
                                onChange={(e) => rescheduleForm.setData('new_start_date', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.leaveRequests.fields.endDate')}
                            id="new_end_date"
                            error={rescheduleForm.errors.new_end_date}
                        >
                            <input
                                id="new_end_date"
                                type="date"
                                value={rescheduleForm.data.new_end_date}
                                onChange={(e) => rescheduleForm.setData('new_end_date', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                    </div>
                    <AdminFormField
                        label={t('pages.leaveRequests.fields.reason')}
                        id="reschedule_reason"
                        error={rescheduleForm.errors.reason}
                    >
                        <textarea
                            id="reschedule_reason"
                            value={rescheduleForm.data.reason}
                            onChange={(e) => rescheduleForm.setData('reason', e.target.value)}
                            rows={3}
                            className="rp-form-input"
                            placeholder={t('pages.leaveRequests.reschedulePlaceholder')}
                        />
                    </AdminFormField>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setRescheduling(null)}>
                            {t('confirm.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={rescheduleForm.processing}>
                            {t('pages.leaveRequests.rescheduleSubmit')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
