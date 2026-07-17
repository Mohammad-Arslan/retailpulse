import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import FormInfoPanel from '@/Components/common/FormInfoPanel';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Timer } from 'lucide-react';
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

            <form onSubmit={submit} className="w-full space-y-5">
                <div className="grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <FormCard className="max-w-none w-full space-y-4 xl:col-span-2">
                        <h3 className="rp-section-title border-b border-rp-border pb-3">
                            {t('pages.attendanceRecords.fields.sectionTitle')}
                        </h3>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <AdminFormField
                                label={t('pages.attendanceRecords.fields.employee')}
                                id="employee_id"
                                error={errors.employee_id}
                                required
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
                                required
                            >
                                <Select
                                    id="branch_id"
                                    value={data.branch_id}
                                    onChange={(value) => setData('branch_id', value ?? '')}
                                    options={branchOptions}
                                />
                            </AdminFormField>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <AdminFormField
                                label={t('pages.attendanceRecords.fields.action')}
                                id="action"
                                error={errors.action}
                                required
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
                                required
                            >
                                <input
                                    id="clocked_at"
                                    type="datetime-local"
                                    value={data.clocked_at}
                                    onChange={(e) => setData('clocked_at', e.target.value)}
                                    className="rp-form-input"
                                />
                            </AdminFormField>
                        </div>

                        {data.action === 'clock_out' && (
                            <AdminFormField
                                label={t('pages.attendanceRecords.fields.openRecordId')}
                                id="open_record_id"
                                error={errors.open_record_id}
                                required
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

                        <div className="flex justify-end gap-3 border-t border-rp-border pt-4">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('admin.attendance.records.index')}>{t('confirm.cancel')}</Link>
                            </Button>
                            <Button type="submit" variant="brand" disabled={processing}>
                                {t('pages.attendanceRecords.saveRecord')}
                            </Button>
                        </div>
                    </FormCard>

                    <FormInfoPanel
                        icon={Timer}
                        title={t('pages.attendanceRecords.infoPanel.title')}
                        className="xl:col-span-1"
                    >
                        <ul>
                            <li>{t('pages.attendanceRecords.infoPanel.tip1')}</li>
                            <li>{t('pages.attendanceRecords.infoPanel.tip2')}</li>
                        </ul>
                    </FormInfoPanel>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
