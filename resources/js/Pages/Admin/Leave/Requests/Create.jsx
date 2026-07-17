import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import FormInfoPanel from '@/Components/common/FormInfoPanel';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const DURATION_TYPES = ['full_day', 'half_day', 'short_leave', 'out_station'];

function Create({ employees, leaveTypes }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        employee_id: '',
        leave_type_id: '',
        start_date: '',
        end_date: '',
        duration_type: 'full_day',
        session: '',
        start_time: '',
        end_time: '',
        reason: '',
    });

    const isSingleDate = data.duration_type === 'half_day' || data.duration_type === 'short_leave';

    const durationTypeOptions = useMemo(
        () =>
            DURATION_TYPES.map((type) => ({
                value: type,
                label: t(`pages.leaveRequests.durationTypes.${type}`),
            })),
        [t],
    );

    const sessionOptions = useMemo(
        () => [
            { value: 'morning', label: t('pages.leaveRequests.sessions.morning') },
            { value: 'afternoon', label: t('pages.leaveRequests.sessions.afternoon') },
        ],
        [t],
    );

    const onDurationTypeChange = (value) => {
        const nextType = value ?? 'full_day';
        setData((current) => ({
            ...current,
            duration_type: nextType,
            end_date: nextType === 'half_day' || nextType === 'short_leave' ? current.start_date : current.end_date,
            session: nextType === 'half_day' ? current.session : '',
            start_time: nextType === 'short_leave' ? current.start_time : '',
            end_time: nextType === 'short_leave' ? current.end_time : '',
        }));
    };

    const onStartDateChange = (value) => {
        setData((current) => ({
            ...current,
            start_date: value,
            end_date: isSingleDate ? value : current.end_date,
        }));
    };

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

            <form onSubmit={submit} className="w-full space-y-5">
                <div className="grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <FormCard className="max-w-none w-full space-y-4 xl:col-span-2">
                        <h3 className="rp-section-title border-b border-rp-border pb-3">
                            {t('pages.leaveRequests.fields.sectionTitle')}
                        </h3>

                        <AdminFormField
                            label={t('pages.leaveRequests.fields.employee')}
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

                        <div className="grid gap-4 sm:grid-cols-2">
                            <AdminFormField
                                label={t('pages.leaveRequests.fields.leaveType')}
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
                                label={t('pages.leaveRequests.fields.durationType')}
                                id="duration_type"
                                error={errors.duration_type}
                                required
                            >
                                <Select
                                    id="duration_type"
                                    value={data.duration_type}
                                    onChange={onDurationTypeChange}
                                    options={durationTypeOptions}
                                />
                            </AdminFormField>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <AdminFormField
                                label={t('pages.leaveRequests.fields.startDate')}
                                id="start_date"
                                error={errors.start_date}
                                required
                            >
                                <input
                                    id="start_date"
                                    type="date"
                                    value={data.start_date}
                                    onChange={(e) => onStartDateChange(e.target.value)}
                                    className="rp-form-input"
                                />
                            </AdminFormField>
                            <AdminFormField
                                label={t('pages.leaveRequests.fields.endDate')}
                                id="end_date"
                                error={errors.end_date}
                                required={!isSingleDate}
                            >
                                <input
                                    id="end_date"
                                    type="date"
                                    value={data.end_date}
                                    onChange={(e) => setData('end_date', e.target.value)}
                                    disabled={isSingleDate}
                                    className="rp-form-input disabled:opacity-60"
                                />
                            </AdminFormField>
                        </div>

                        {data.duration_type === 'half_day' && (
                            <AdminFormField
                                label={t('pages.leaveRequests.fields.session')}
                                id="session"
                                error={errors.session}
                                required
                            >
                                <Select
                                    id="session"
                                    value={data.session}
                                    onChange={(value) => setData('session', value ?? '')}
                                    options={sessionOptions}
                                />
                            </AdminFormField>
                        )}

                        {data.duration_type === 'short_leave' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <AdminFormField
                                    label={t('pages.leaveRequests.fields.startTime')}
                                    id="start_time"
                                    error={errors.start_time}
                                    required
                                >
                                    <input
                                        id="start_time"
                                        type="time"
                                        value={data.start_time}
                                        onChange={(e) => setData('start_time', e.target.value)}
                                        className="rp-form-input"
                                    />
                                </AdminFormField>
                                <AdminFormField
                                    label={t('pages.leaveRequests.fields.endTime')}
                                    id="end_time"
                                    error={errors.end_time}
                                    required
                                >
                                    <input
                                        id="end_time"
                                        type="time"
                                        value={data.end_time}
                                        onChange={(e) => setData('end_time', e.target.value)}
                                        className="rp-form-input"
                                    />
                                </AdminFormField>
                            </div>
                        )}

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

                        <div className="flex justify-end gap-3 border-t border-rp-border pt-4">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('admin.leave.requests.index')}>{t('confirm.cancel')}</Link>
                            </Button>
                            <Button type="submit" variant="brand" disabled={processing}>
                                {t('pages.leaveRequests.submitRequest')}
                            </Button>
                        </div>
                    </FormCard>

                    <FormInfoPanel
                        icon={CalendarRange}
                        title={t('pages.leaveRequests.infoPanel.title')}
                        className="xl:col-span-1"
                    >
                        <ul>
                            <li>{t('pages.leaveRequests.infoPanel.fullDay')}</li>
                            <li>{t('pages.leaveRequests.infoPanel.halfDay')}</li>
                            <li>{t('pages.leaveRequests.infoPanel.shortLeave')}</li>
                            <li>{t('pages.leaveRequests.infoPanel.outStation')}</li>
                        </ul>
                    </FormInfoPanel>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
