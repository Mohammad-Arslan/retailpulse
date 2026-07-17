import AdminFormField from '@/Components/common/AdminFormField';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, useForm } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

const WEEKDAYS = [0, 1, 2, 3, 4, 5, 6];

function Index({ entities = [], holidayCalendars = [] }) {
    const { t } = useTranslation();
    const can = useCan();
    const [activeEntityId, setActiveEntityId] = useState(String(entities[0]?.legal_entity_id ?? ''));

    const activeEntity = useMemo(
        () => entities.find((e) => String(e.legal_entity_id) === activeEntityId) ?? entities[0],
        [activeEntityId, entities],
    );

    const form = useForm({
        legal_entity_id: activeEntity?.legal_entity_id ?? '',
        default_holiday_calendar_id: activeEntity?.default_holiday_calendar_id
            ? String(activeEntity.default_holiday_calendar_id)
            : '',
        employee_code_sequence_key: activeEntity?.employee_code_sequence_key ?? '',
        settings_json: {
            default_leave_fiscal_year_mode: activeEntity?.settings_json?.default_leave_fiscal_year_mode ?? 'calendar_year',
            require_default_cost_centre: activeEntity?.settings_json?.require_default_cost_centre ?? false,
            work_hours_per_day: activeEntity?.settings_json?.work_hours_per_day ?? 8,
            weekend_days: activeEntity?.settings_json?.weekend_days ?? [0, 6],
        },
    });

    const weekdayOptions = useMemo(
        () => WEEKDAYS.map((day) => ({ value: day, label: t(`pages.hrSettings.weekdays.${day}`) })),
        [t],
    );

    const toggleWeekendDay = (day) => {
        const current = form.data.settings_json.weekend_days ?? [];
        const next = current.includes(day) ? current.filter((d) => d !== day) : [...current, day];
        form.setData('settings_json', { ...form.data.settings_json, weekend_days: next });
    };

    const entityOptions = useMemo(
        () => entities.map((e) => ({ value: String(e.legal_entity_id), label: e.legal_entity_name })),
        [entities],
    );

    const calendarOptions = useMemo(
        () => [
            { value: '', label: t('pages.hrSettings.noHolidayCalendar') },
            ...holidayCalendars.map((c) => ({ value: String(c.id), label: `${c.code} — ${c.name}` })),
        ],
        [holidayCalendars, t],
    );

    const fiscalYearModeOptions = useMemo(
        () => [
            { value: 'calendar_year', label: t('pages.hrSettings.fiscalYearModes.calendarYear') },
            { value: 'fiscal_year', label: t('pages.hrSettings.fiscalYearModes.fiscalYear') },
            { value: 'hire_anniversary', label: t('pages.hrSettings.fiscalYearModes.hireAnniversary') },
        ],
        [t],
    );

    const switchEntity = (entityId) => {
        setActiveEntityId(entityId);
        const entity = entities.find((e) => String(e.legal_entity_id) === entityId);
        if (!entity) {
            return;
        }
        form.setData({
            legal_entity_id: entity.legal_entity_id,
            default_holiday_calendar_id: entity.default_holiday_calendar_id
                ? String(entity.default_holiday_calendar_id)
                : '',
            employee_code_sequence_key: entity.employee_code_sequence_key ?? '',
            settings_json: {
                default_leave_fiscal_year_mode: entity.settings_json?.default_leave_fiscal_year_mode ?? 'calendar_year',
                require_default_cost_centre: entity.settings_json?.require_default_cost_centre ?? false,
                work_hours_per_day: entity.settings_json?.work_hours_per_day ?? 8,
                weekend_days: entity.settings_json?.weekend_days ?? [0, 6],
            },
        });
        form.clearErrors();
    };

    const submit = (e) => {
        e.preventDefault();
        form.put(route('admin.hr.settings.update'), { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('pages.hrSettings.indexTitle')} />
            <PageHeader
                title={t('pages.hrSettings.indexTitle')}
                description={t('pages.hrSettings.indexDescription')}
            />

            <div className="mx-auto max-w-3xl space-y-6">
                <AdminFormField label={t('pages.hrSettings.fields.legalEntity')}>
                    <Select
                        value={activeEntityId}
                        options={entityOptions}
                        onChange={(v) => switchEntity(v ?? activeEntityId)}
                    />
                </AdminFormField>

                <form onSubmit={submit} className="relative space-y-5 overflow-visible rounded-xl border border-rp-border bg-rp-surface p-6">
                    <div className="flex items-center gap-2">
                        <Settings2 className="h-5 w-5 text-teal-600" />
                        <h2 className="text-base font-semibold text-rp-text">{activeEntity?.legal_entity_name}</h2>
                    </div>

                    <AdminFormField
                        label={t('pages.hrSettings.fields.defaultHolidayCalendar')}
                        id="default_holiday_calendar_id"
                        error={form.errors.default_holiday_calendar_id}
                        hint={t('pages.hrSettings.hints.defaultHolidayCalendar')}
                        className="relative z-20"
                    >
                        <Select
                            id="default_holiday_calendar_id"
                            value={form.data.default_holiday_calendar_id}
                            options={calendarOptions}
                            onChange={(v) => form.setData('default_holiday_calendar_id', v ?? '')}
                            isClearable
                        />
                    </AdminFormField>

                    <AdminFormField
                        label={t('pages.hrSettings.fields.employeeCodeSequenceKey')}
                        id="employee_code_sequence_key"
                        error={form.errors.employee_code_sequence_key}
                        hint={t('pages.hrSettings.hints.employeeCodeSequenceKey')}
                        className="relative z-10"
                    >
                        <input
                            id="employee_code_sequence_key"
                            className="rp-form-input w-full font-mono"
                            value={form.data.employee_code_sequence_key}
                            onChange={(e) => form.setData('employee_code_sequence_key', e.target.value)}
                            placeholder={t('pages.hrSettings.placeholders.employeeCodeSequenceKey')}
                        />
                    </AdminFormField>

                    <AdminFormField
                        label={t('pages.hrSettings.fields.leaveFiscalYearMode')}
                        id="leave_fiscal_year_mode"
                        error={form.errors['settings_json.default_leave_fiscal_year_mode']}
                        hint={t('pages.hrSettings.hints.leaveFiscalYearMode')}
                    >
                        <Select
                            id="leave_fiscal_year_mode"
                            value={form.data.settings_json.default_leave_fiscal_year_mode || 'calendar_year'}
                            options={fiscalYearModeOptions}
                            onChange={(v) =>
                                form.setData('settings_json', {
                                    ...form.data.settings_json,
                                    default_leave_fiscal_year_mode: v ?? 'calendar_year',
                                })
                            }
                        />
                    </AdminFormField>

                    <label className="rp-checkbox-label">
                        <input
                            type="checkbox"
                            checked={!!form.data.settings_json.require_default_cost_centre}
                            onChange={(e) =>
                                form.setData('settings_json', {
                                    ...form.data.settings_json,
                                    require_default_cost_centre: e.target.checked,
                                })
                            }
                            className="accent-teal-600"
                        />
                        {t('pages.hrSettings.fields.requireDefaultCostCentre')}
                    </label>

                    <AdminFormField
                        label={t('pages.hrSettings.fields.workHoursPerDay')}
                        id="work_hours_per_day"
                        error={form.errors['settings_json.work_hours_per_day']}
                        hint={t('pages.hrSettings.hints.workHoursPerDay')}
                    >
                        <input
                            id="work_hours_per_day"
                            type="number"
                            step="0.5"
                            min="1"
                            max="24"
                            className="rp-form-input w-full"
                            value={form.data.settings_json.work_hours_per_day}
                            onChange={(e) =>
                                form.setData('settings_json', {
                                    ...form.data.settings_json,
                                    work_hours_per_day: e.target.value,
                                })
                            }
                        />
                    </AdminFormField>

                    <AdminFormField
                        label={t('pages.hrSettings.fields.weekendDays')}
                        error={form.errors['settings_json.weekend_days']}
                        hint={t('pages.hrSettings.hints.weekendDays')}
                    >
                        <div className="flex flex-wrap gap-3">
                            {weekdayOptions.map((option) => (
                                <label key={option.value} className="rp-checkbox-label">
                                    <input
                                        type="checkbox"
                                        checked={(form.data.settings_json.weekend_days ?? []).includes(option.value)}
                                        onChange={() => toggleWeekendDay(option.value)}
                                        className="accent-teal-600"
                                    />
                                    {option.label}
                                </label>
                            ))}
                        </div>
                    </AdminFormField>

                    {can('hr.manage-settings') && (
                        <div className="flex justify-end pt-2">
                            <Button type="submit" variant="brand" disabled={form.processing}>
                                {t('pages.hrSettings.save')}
                            </Button>
                        </div>
                    )}
                </form>
            </div>
        </>
    );
}

export default withAdminLayout(Index);
