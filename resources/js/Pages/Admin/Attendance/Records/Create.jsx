import AdminFormField from '@/Components/common/AdminFormField';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Create({ employees, branches, actions }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        employee_id: '',
        branch_id: '',
        action: 'clock_in',
        clocked_at: '',
        open_record_id: '',
    });

    const employeeOptions = useMemo(
        () => [
            { value: '', label: t('pages.attendanceRecords.selectEmployee') },
            ...employees.map((employee) => ({
                value: String(employee.id),
                label: `${employee.first_name} ${employee.last_name} (${employee.employee_code})`,
            })),
        ],
        [employees, t],
    );

    const branchOptions = useMemo(
        () => [
            { value: '', label: t('pages.attendanceRecords.selectBranch') },
            ...branches.map((branch) => ({ value: String(branch.id), label: branch.name })),
        ],
        [branches, t],
    );

    const actionOptions = useMemo(
        () =>
            actions.map((action) => ({
                value: action,
                label: t(`pages.attendanceRecords.actions.${action}`),
            })),
        [actions, t],
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.attendance.records.store'));
    };

    return (
        <>
            <Head title={t('pages.attendanceRecords.manualClockTitle')} />
            <PageHeader
                title={t('pages.attendanceRecords.manualClockTitle')}
                description={t('pages.attendanceRecords.manualClockDescription')}
            >
                <Link href={route('admin.attendance.records.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="mx-auto max-w-2xl space-y-6">
                <div className="rp-card space-y-4 p-6">
                    <AdminFormField
                        label={t('pages.attendanceRecords.fields.employee')}
                        id="employee_id"
                        error={errors.employee_id}
                    >
                        <Select
                            id="employee_id"
                            value={data.employee_id}
                            onChange={(value) => setData('employee_id', value ?? '')}
                            options={employeeOptions}
                        />
                    </AdminFormField>

                    <AdminFormField
                        label={t('pages.attendanceRecords.fields.branch')}
                        id="branch_id"
                        error={errors.branch_id}
                    >
                        <Select
                            id="branch_id"
                            value={data.branch_id}
                            onChange={(value) => setData('branch_id', value ?? '')}
                            options={branchOptions}
                        />
                    </AdminFormField>

                    <AdminFormField
                        label={t('pages.attendanceRecords.fields.action')}
                        id="action"
                        error={errors.action}
                    >
                        <Select
                            id="action"
                            value={data.action}
                            onChange={(value) => setData('action', value ?? 'clock_in')}
                            options={actionOptions}
                        />
                    </AdminFormField>

                    <AdminFormField
                        label={t('pages.attendanceRecords.fields.clockedAt')}
                        id="clocked_at"
                        error={errors.clocked_at}
                    >
                        <input
                            id="clocked_at"
                            type="datetime-local"
                            value={data.clocked_at}
                            onChange={(e) => setData('clocked_at', e.target.value)}
                            className="rp-form-input"
                        />
                    </AdminFormField>

                    {data.action === 'clock_out' && (
                        <AdminFormField
                            label={t('pages.attendanceRecords.fields.openRecordId')}
                            id="open_record_id"
                            error={errors.open_record_id}
                        >
                            <input
                                id="open_record_id"
                                type="number"
                                value={data.open_record_id}
                                onChange={(e) => setData('open_record_id', e.target.value)}
                                placeholder={t('pages.attendanceRecords.openRecordPlaceholder')}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                    )}
                </div>

                <div className="flex justify-end gap-3">
                    <Link href={route('admin.attendance.records.index')} className="rp-btn-outline">
                        {t('common.cancel')}
                    </Link>
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('pages.attendanceRecords.saveRecord')}
                    </button>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
