import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
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
                    <div>
                        <label className="rp-label">{t('pages.attendanceRecords.fields.employee')}</label>
                        <Select
                            value={data.employee_id}
                            onChange={(e) => setData('employee_id', e.target.value)}
                            className="w-full"
                        >
                            <option value="">{t('pages.attendanceRecords.selectEmployee')}</option>
                            {employees.map((employee) => (
                                <option key={employee.id} value={employee.id}>
                                    {employee.first_name} {employee.last_name} ({employee.employee_code})
                                </option>
                            ))}
                        </Select>
                        {errors.employee_id && <p className="mt-1 text-sm text-red-600">{errors.employee_id}</p>}
                    </div>

                    <div>
                        <label className="rp-label">{t('pages.attendanceRecords.fields.branch')}</label>
                        <Select
                            value={data.branch_id}
                            onChange={(e) => setData('branch_id', e.target.value)}
                            className="w-full"
                        >
                            <option value="">{t('pages.attendanceRecords.selectBranch')}</option>
                            {branches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.name}
                                </option>
                            ))}
                        </Select>
                        {errors.branch_id && <p className="mt-1 text-sm text-red-600">{errors.branch_id}</p>}
                    </div>

                    <div>
                        <label className="rp-label">{t('pages.attendanceRecords.fields.action')}</label>
                        <Select
                            value={data.action}
                            onChange={(e) => setData('action', e.target.value)}
                            className="w-full"
                        >
                            {actions.map((action) => (
                                <option key={action} value={action}>
                                    {t(`pages.attendanceRecords.actions.${action}`)}
                                </option>
                            ))}
                        </Select>
                        {errors.action && <p className="mt-1 text-sm text-red-600">{errors.action}</p>}
                    </div>

                    <div>
                        <label className="rp-label">{t('pages.attendanceRecords.fields.clockedAt')}</label>
                        <input
                            type="datetime-local"
                            value={data.clocked_at}
                            onChange={(e) => setData('clocked_at', e.target.value)}
                            className="rp-input w-full"
                        />
                        {errors.clocked_at && <p className="mt-1 text-sm text-red-600">{errors.clocked_at}</p>}
                    </div>

                    {data.action === 'clock_out' && (
                        <div>
                            <label className="rp-label">{t('pages.attendanceRecords.fields.openRecordId')}</label>
                            <input
                                type="number"
                                value={data.open_record_id}
                                onChange={(e) => setData('open_record_id', e.target.value)}
                                placeholder={t('pages.attendanceRecords.openRecordPlaceholder')}
                                className="rp-input w-full"
                            />
                            {errors.open_record_id && (
                                <p className="mt-1 text-sm text-red-600">{errors.open_record_id}</p>
                            )}
                        </div>
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
