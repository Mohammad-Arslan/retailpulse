import AdminFormField from '@/Components/common/AdminFormField';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Create({ employees, leaveTypes }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        employee_id: '',
        leave_type_id: '',
        start_date: '',
        end_date: '',
        reason: '',
    });

    const employeeOptions = useMemo(
        () => [
            { value: '', label: t('pages.leaveRequests.selectEmployee') },
            ...employees.map((employee) => ({
                value: String(employee.id),
                label: `${employee.first_name} ${employee.last_name} (${employee.employee_code})`,
            })),
        ],
        [employees, t],
    );

    const leaveTypeOptions = useMemo(
        () => [
            { value: '', label: t('pages.leaveRequests.selectLeaveType') },
            ...leaveTypes.map((type) => ({
                value: String(type.id),
                label: `${type.name} (${type.code})`,
            })),
        ],
        [leaveTypes, t],
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.leave.requests.store'));
    };

    return (
        <>
            <Head title={t('pages.leaveRequests.createTitle')} />
            <PageHeader
                title={t('pages.leaveRequests.createTitle')}
                description={t('pages.leaveRequests.createDescription')}
            >
                <Link href={route('admin.leave.requests.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="mx-auto max-w-2xl space-y-6">
                <div className="rp-card space-y-4 p-6">
                    <AdminFormField
                        label={t('pages.leaveRequests.fields.employee')}
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
                        label={t('pages.leaveRequests.fields.leaveType')}
                        id="leave_type_id"
                        error={errors.leave_type_id}
                    >
                        <Select
                            id="leave_type_id"
                            value={data.leave_type_id}
                            onChange={(value) => setData('leave_type_id', value ?? '')}
                            options={leaveTypeOptions}
                        />
                    </AdminFormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.leaveRequests.fields.startDate')}
                            id="start_date"
                            error={errors.start_date}
                        >
                            <input
                                id="start_date"
                                type="date"
                                value={data.start_date}
                                onChange={(e) => setData('start_date', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.leaveRequests.fields.endDate')}
                            id="end_date"
                            error={errors.end_date}
                        >
                            <input
                                id="end_date"
                                type="date"
                                value={data.end_date}
                                onChange={(e) => setData('end_date', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                    </div>

                    <AdminFormField label={t('pages.leaveRequests.fields.reason')} id="reason" error={errors.reason}>
                        <textarea
                            id="reason"
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                            rows={3}
                            className="rp-form-input"
                            placeholder={t('pages.leaveRequests.reasonPlaceholder')}
                        />
                    </AdminFormField>
                </div>

                <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" asChild>
                        <Link href={route('admin.leave.requests.index')}>{t('confirm.cancel')}</Link>
                    </Button>
                    <Button type="submit" variant="brand" disabled={processing}>
                        {t('pages.leaveRequests.submitRequest')}
                    </Button>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
