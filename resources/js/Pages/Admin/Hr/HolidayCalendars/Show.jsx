import AdminFormField from '@/Components/common/AdminFormField';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function Show({ calendar, dates = [], assignments = [], employees = [], branches = [], legalEntities = [] }) {
    const { t } = useTranslation();
    const can = useCan();

    const dateForm = useForm({
        holiday_date: '',
        name: '',
        holiday_type: 'public',
        is_paid: true,
    });

    const assignForm = useForm({
        assignable_type: 'legal_entity',
        assignable_id: calendar.legal_entity_id ? String(calendar.legal_entity_id) : '',
        effective_from: new Date().toISOString().slice(0, 10),
        effective_to: '',
        priority: '0',
        status: 'active',
    });

    const holidayTypeOptions = useMemo(
        () => [
            { value: 'public', label: t('pages.holidayCalendars.holidayTypes.public') },
            { value: 'optional', label: t('pages.holidayCalendars.holidayTypes.optional') },
            { value: 'company', label: t('pages.holidayCalendars.holidayTypes.company') },
        ],
        [t],
    );

    const assignTypeOptions = useMemo(
        () => [
            { value: 'legal_entity', label: t('pages.holidayCalendars.assignTypes.legalEntity') },
            { value: 'branch', label: t('pages.holidayCalendars.assignTypes.branch') },
            { value: 'employee', label: t('pages.holidayCalendars.assignTypes.employee') },
        ],
        [t],
    );

    const assignableOptions = useMemo(() => {
        if (assignForm.data.assignable_type === 'employee') {
            return employees.map((e) => ({
                value: String(e.id),
                label: `${e.employee_code} — ${e.first_name} ${e.last_name}`,
            }));
        }
        if (assignForm.data.assignable_type === 'branch') {
            return branches.map((b) => ({ value: String(b.id), label: b.name }));
        }
        return legalEntities.map((e) => ({ value: String(e.id), label: e.legal_name }));
    }, [assignForm.data.assignable_type, branches, employees, legalEntities]);

    return (
        <>
            <Head title={calendar.name} />
            <PageHeader title={calendar.name} description={calendar.code}>
                <Link href={route('admin.hr.holiday-calendars.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <section className="mb-8 rounded-lg border border-rp-border p-4">
                <h2 className="mb-3 text-lg font-semibold">{t('pages.holidayCalendars.datesTitle')}</h2>
                {can('holiday.manage') && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            dateForm.post(route('admin.hr.holiday-calendars.dates.store', calendar.id), {
                                preserveScroll: true,
                                onSuccess: () => dateForm.reset(),
                            });
                        }}
                        className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <AdminFormField
                            label={t('pages.holidayCalendars.fields.date')}
                            id="holiday_date"
                            error={dateForm.errors.holiday_date}
                        >
                            <input
                                id="holiday_date"
                                type="date"
                                className="rp-form-input"
                                value={dateForm.data.holiday_date}
                                onChange={(e) => dateForm.setData('holiday_date', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.holidayCalendars.fields.dateName')}
                            id="date_name"
                            error={dateForm.errors.name}
                        >
                            <input
                                id="date_name"
                                className="rp-form-input"
                                value={dateForm.data.name}
                                onChange={(e) => dateForm.setData('name', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.holidayCalendars.fields.holidayType')}
                            id="holiday_type"
                            error={dateForm.errors.holiday_type}
                        >
                            <Select
                                id="holiday_type"
                                value={dateForm.data.holiday_type}
                                onChange={(value) => dateForm.setData('holiday_type', value ?? 'public')}
                                options={holidayTypeOptions}
                            />
                        </AdminFormField>
                        <div className="flex items-end">
                            <Button type="submit" variant="brand" disabled={dateForm.processing} className="w-full">
                                {t('pages.holidayCalendars.addDate')}
                            </Button>
                        </div>
                    </form>
                )}
                <ul className="divide-y divide-rp-border rounded-lg border border-rp-border">
                    {dates.length === 0 && (
                        <li className="p-4 text-sm text-rp-text-muted">{t('common.noResults')}</li>
                    )}
                    {dates.map((d) => (
                        <li key={d.id} className="flex items-center justify-between p-3 text-sm">
                            <span>
                                {d.holiday_date} — {d.name} (
                                {t(`pages.holidayCalendars.holidayTypes.${d.holiday_type}`, {
                                    defaultValue: d.holiday_type,
                                })}
                                )
                            </span>
                            {can('holiday.manage') && (
                                <button
                                    type="button"
                                    className="text-xs text-red-600 hover:underline"
                                    onClick={() =>
                                        router.delete(
                                            route('admin.hr.holiday-calendars.dates.destroy', [calendar.id, d.id]),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('common.delete')}
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            </section>

            <section className="rounded-lg border border-rp-border p-4">
                <h2 className="mb-3 text-lg font-semibold">{t('pages.holidayCalendars.assignmentsTitle')}</h2>
                {can('holiday.manage') && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            assignForm.post(route('admin.hr.holiday-calendars.assignments.store', calendar.id), {
                                preserveScroll: true,
                                onSuccess: () => assignForm.reset('effective_to'),
                            });
                        }}
                        className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <AdminFormField
                            label={t('pages.holidayCalendars.fields.assignableType')}
                            id="assignable_type"
                            error={assignForm.errors.assignable_type}
                        >
                            <Select
                                id="assignable_type"
                                value={assignForm.data.assignable_type}
                                onChange={(value) => {
                                    assignForm.setData('assignable_type', value ?? 'legal_entity');
                                    assignForm.setData('assignable_id', '');
                                }}
                                options={assignTypeOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.holidayCalendars.fields.assignable')}
                            id="assignable_id"
                            error={assignForm.errors.assignable_id}
                        >
                            <Select
                                id="assignable_id"
                                value={assignForm.data.assignable_id}
                                onChange={(value) => assignForm.setData('assignable_id', value ?? '')}
                                options={assignableOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.holidayCalendars.fields.effectiveFrom')}
                            id="effective_from"
                            error={assignForm.errors.effective_from}
                        >
                            <input
                                id="effective_from"
                                type="date"
                                className="rp-form-input"
                                value={assignForm.data.effective_from}
                                onChange={(e) => assignForm.setData('effective_from', e.target.value)}
                                required
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.holidayCalendars.fields.priority')}
                            id="priority"
                            error={assignForm.errors.priority}
                        >
                            <input
                                id="priority"
                                type="number"
                                className="rp-form-input"
                                value={assignForm.data.priority}
                                onChange={(e) => assignForm.setData('priority', e.target.value)}
                            />
                        </AdminFormField>
                        <div className="flex items-end lg:col-span-2">
                            <Button type="submit" variant="brand" disabled={assignForm.processing}>
                                {t('pages.holidayCalendars.addAssignment')}
                            </Button>
                        </div>
                    </form>
                )}
                <ul className="divide-y divide-rp-border rounded-lg border border-rp-border">
                    {assignments.length === 0 && (
                        <li className="p-4 text-sm text-rp-text-muted">{t('common.noResults')}</li>
                    )}
                    {assignments.map((a) => (
                        <li key={a.id} className="flex items-center justify-between p-3 text-sm">
                            <span>
                                {a.assignable_label} ({a.assignable_type}) — {a.effective_from}
                                {a.effective_to ? ` → ${a.effective_to}` : ''}
                            </span>
                            {can('holiday.manage') && (
                                <button
                                    type="button"
                                    className="text-xs text-red-600 hover:underline"
                                    onClick={() =>
                                        router.delete(
                                            route('admin.hr.holiday-calendars.assignments.destroy', [
                                                calendar.id,
                                                a.id,
                                            ]),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('common.delete')}
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            </section>
        </>
    );
}

export default withAdminLayout(Show);
