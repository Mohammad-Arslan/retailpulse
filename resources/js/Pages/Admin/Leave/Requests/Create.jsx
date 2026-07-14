import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
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
                    <div>
                        <label className="rp-label">{t('pages.leaveRequests.fields.employee')}</label>
                        <Select
                            value={data.employee_id}
                            onChange={(e) => setData('employee_id', e.target.value)}
                            className="w-full"
                        >
                            <option value="">{t('pages.leaveRequests.selectEmployee')}</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.first_name} {employee.last_name} ({employee.employee_code})
                                </option>
                            ))}
                        </Select>
                        {errors.employee_id && <p className="mt-1 text-sm text-red-600">{errors.employee_id}</p>}
                    </div>

                    <div>
                        <label className="rp-label">{t('pages.leaveRequests.fields.leaveType')}</label>
                        <Select
                            value={data.leave_type_id}
                            onChange={(e) => setData('leave_type_id', e.target.value)}
                            className="w-full"
                        >
                            <option value="">{t('pages.leaveRequests.selectLeaveType')}</option>
                            {leaveTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name} ({type.code})
                                </option>
                            ))}
                        </Select>
                        {errors.leave_type_id && (
                            <p className="mt-1 text-sm text-red-600">{errors.leave_type_id}</p>
                        )}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="rp-label">{t('pages.leaveRequests.fields.startDate')}</label>
                            <input
                                type="date"
                                value={data.start_date}
                                onChange={(e) => setData('start_date', e.target.value)}
                                className="rp-input w-full"
                            />
                            {errors.start_date && (
                                <p className="mt-1 text-sm text-red-600">{errors.start_date}</p>
                            )}
                        </div>
                        <div>
                            <label className="rp-label">{t('pages.leaveRequests.fields.endDate')}</label>
                            <input
                                type="date"
                                value={data.end_date}
                                onChange={(e) => setData('end_date', e.target.value)}
                                className="rp-input w-full"
                            />
                            {errors.end_date && <p className="mt-1 text-sm text-red-600">{errors.end_date}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="rp-label">{t('pages.leaveRequests.fields.reason')}</label>
                        <textarea
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                            rows={3}
                            className="rp-input w-full"
                        />
                        {errors.reason && <p className="mt-1 text-sm text-red-600">{errors.reason}</p>}
                    </div>
                </div>

                <div className="flex justify-end gap-3">
                    <Link href={route('admin.leave.requests.index')} className="rp-btn-outline">
                        {t('common.cancel')}
                    </Link>
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('pages.leaveRequests.submitRequest')}
                    </button>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
