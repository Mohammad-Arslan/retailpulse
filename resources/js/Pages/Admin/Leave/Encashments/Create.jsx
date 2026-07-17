import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import FormInfoPanel from '@/Components/common/FormInfoPanel';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Banknote } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Create({ employees, leaveTypes }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        employee_id: '',
        leave_type_id: '',
        days: '',
        reason: '',
    });

    const employeeOptions = useMemo(
        () => [
            { value: '', label: t('pages.leaveEncashments.selectEmployee') },
            ...employees.map((employee) => ({
                value: String(employee.id),
                label: `${employee.first_name} ${employee.last_name} (${employee.employee_code})`,
            })),
        ],
        [employees, t],
    );

    const leaveTypeOptions = useMemo(
        () => [
            { value: '', label: t('pages.leaveEncashments.selectLeaveType') },
            ...leaveTypes.map((type) => ({
                value: String(type.id),
                label: `${type.name} (${type.code})`,
            })),
        ],
        [leaveTypes, t],
    );

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.leave.encashments.store'));
    };

    return (
        <>
            <Head title={t('pages.leaveEncashments.createTitle')} />
            <PageHeader
                title={t('pages.leaveEncashments.createTitle')}
                description={t('pages.leaveEncashments.createDescription')}
            >
                <Link href={route('admin.leave.encashments.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="w-full space-y-5">
                <div className="grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <FormCard className="max-w-none w-full space-y-4 xl:col-span-2">
                        <h3 className="rp-section-title border-b border-rp-border pb-3">
                            {t('pages.leaveEncashments.fields.sectionTitle')}
                        </h3>

                        <AdminFormField
                            label={t('pages.leaveEncashments.fields.employee')}
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
                            label={t('pages.leaveEncashments.fields.leaveType')}
                            id="leave_type_id"
                            error={errors.leave_type_id}
                            required
                        >
                            <Select
                                id="leave_type_id"
                                value={data.leave_type_id}
                                onChange={(value) => setData('leave_type_id', value ?? '')}
                                options={leaveTypeOptions}
                            />
                        </AdminFormField>

                        <AdminFormField
                            label={t('pages.leaveEncashments.fields.days')}
                            id="days"
                            error={errors.days}
                            required
                        >
                            <input
                                id="days"
                                type="number"
                                step="0.25"
                                min="0.25"
                                value={data.days}
                                onChange={(e) => setData('days', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>

                        <AdminFormField label={t('pages.leaveEncashments.fields.reason')} id="reason" error={errors.reason}>
                            <textarea
                                id="reason"
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                                rows={3}
                                className="rp-form-input"
                                placeholder={t('pages.leaveEncashments.reasonPlaceholder')}
                            />
                        </AdminFormField>

                        <div className="flex justify-end gap-3 border-t border-rp-border pt-4">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('admin.leave.encashments.index')}>{t('confirm.cancel')}</Link>
                            </Button>
                            <Button type="submit" variant="brand" disabled={processing}>
                                {t('pages.leaveEncashments.submitRequest')}
                            </Button>
                        </div>
                    </FormCard>

                    <FormInfoPanel
                        icon={Banknote}
                        title={t('pages.leaveEncashments.infoPanel.title')}
                        className="xl:col-span-1"
                    >
                        <ol>
                            <li>{t('pages.leaveEncashments.infoPanel.step1')}</li>
                            <li>{t('pages.leaveEncashments.infoPanel.step2')}</li>
                            <li>{t('pages.leaveEncashments.infoPanel.step3')}</li>
                        </ol>
                    </FormInfoPanel>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
