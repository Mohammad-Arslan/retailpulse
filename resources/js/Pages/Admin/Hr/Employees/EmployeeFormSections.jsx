import EmployeeImageAttachments from '@/Components/admin/hr/EmployeeImageAttachments';
import AdminFormField from '@/Components/common/AdminFormField';
import { Button } from '@/Components/ui/button';
import Select from '@/Components/ui/select';
import { Link } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function setNested(setData, path, value) {
    setData(path, value);
}

const mapOptions = (items, valueKey = 'id', labelFn) =>
    items.map((item) => ({
        value: String(item[valueKey]),
        label: labelFn ? labelFn(item) : item.name,
    }));

export function useEmployeeFormOptions({
    legalEntities,
    branches,
    costCentres,
    departments,
    designations,
    grades,
    managers,
    salaryStructures,
    currencies,
    holidayCalendars,
    t,
}) {
    const entityOptions = useMemo(
        () => [
            { value: '', label: t('pages.hrEmployees.selectLegalEntity') },
            ...mapOptions(legalEntities, 'id', (e) => e.legal_name),
        ],
        [legalEntities, t],
    );
    const branchOptions = useMemo(
        () => [
            { value: '', label: t('pages.hrEmployees.selectBranch') },
            ...mapOptions(branches, 'id', (b) => b.name),
        ],
        [branches, t],
    );
    const none = { value: '', label: t('common.none') };
    const departmentOptions = useMemo(
        () => [none, ...mapOptions(departments, 'id', (d) => `${d.code} — ${d.name}`)],
        [departments, t],
    );
    const designationOptions = useMemo(
        () => [none, ...mapOptions(designations, 'id', (d) => `${d.code} — ${d.name}`)],
        [designations, t],
    );
    const gradeOptions = useMemo(
        () => [none, ...mapOptions(grades, 'id', (g) => `${g.code} — ${g.name}`)],
        [grades, t],
    );
    const managerOptions = useMemo(
        () => [
            none,
            ...managers.map((m) => ({
                value: String(m.id),
                label: `${m.employee_code} — ${m.first_name} ${m.last_name}`,
            })),
        ],
        [managers, t],
    );
    const costCentreOptions = useMemo(
        () => [none, ...mapOptions(costCentres, 'id', (c) => `${c.code} — ${c.name}`)],
        [costCentres, t],
    );
    const structureOptions = useMemo(
        () => [none, ...mapOptions(salaryStructures, 'id', (s) => `${s.code} — ${s.name}`)],
        [salaryStructures, t],
    );
    const currencyOptions = useMemo(
        () => [
            { value: '', label: t('common.selectCurrency') },
            ...currencies.map((c) => ({ value: c.code, label: `${c.code} — ${c.name}` })),
        ],
        [currencies, t],
    );
    const calendarOptions = useMemo(
        () => [
            { value: '', label: t('common.none') },
            ...holidayCalendars.map((c) => ({ value: String(c.id), label: `${c.code} — ${c.name}` })),
        ],
        [holidayCalendars, t],
    );

    return {
        entityOptions,
        branchOptions,
        departmentOptions,
        designationOptions,
        gradeOptions,
        managerOptions,
        costCentreOptions,
        structureOptions,
        currencyOptions,
        calendarOptions,
    };
}

export default function EmployeeFormSections({
    section,
    data,
    setData,
    errors,
    readOnly = false,
    employee = null,
    legalEntities = [],
    branches = [],
    costCentres = [],
    departments = [],
    designations = [],
    grades = [],
    managers = [],
    salaryStructures = [],
    currencies = [],
    holidayCalendars = [],
    employmentTypes = [],
    genders = [],
    maritalStatuses = [],
    attachmentTypes = [],
    weekDays = [],
    maxImages = 10,
    hideSecondaryBranches = false,
    showBanksOptionalHint = false,
}) {
    const { t } = useTranslation();

    const {
        entityOptions,
        branchOptions,
        departmentOptions,
        designationOptions,
        gradeOptions,
        managerOptions,
        costCentreOptions,
        structureOptions,
        currencyOptions,
        calendarOptions,
    } = useEmployeeFormOptions({
        legalEntities,
        branches,
        costCentres,
        departments,
        designations,
        grades,
        managers,
        salaryStructures,
        currencies,
        holidayCalendars,
        t,
    });

    const field = (path, value) => {
        if (readOnly) {
            return;
        }
        setNested(setData, path, value);
    };

    const addDependent = () => {
        setData('dependents', [
            ...(data.dependents ?? []),
            {
                name: '',
                relation: '',
                date_of_birth: '',
                gender: '',
                national_id: '',
                phone: '',
                is_emergency_contact: false,
            },
        ]);
    };

    const addBank = () => {
        setData('bank_accounts', [
            ...(data.bank_accounts ?? []),
            {
                label: '',
                bank_name: '',
                account_number: '',
                iban: '',
                currency_code: currencies[0]?.code ?? '',
                payment_method: data.payment_method || '',
                is_primary: (data.bank_accounts ?? []).length === 0,
            },
        ]);
    };

    const addBranchAssignment = () => {
        setData('branch_assignments', [
            ...(data.branch_assignments ?? []),
            {
                branch_id: '',
                effective_from: new Date().toISOString().slice(0, 10),
                effective_to: '',
                status: 'active',
            },
        ]);
    };

    if (section === 'basic') {
        return (
            <div className="grid gap-4 sm:grid-cols-2">
                <AdminFormField label={t('pages.hrEmployees.fields.title')} error={errors.title}>
                    <input
                        className="rp-form-input"
                        value={data.title}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.title')}
                        onChange={(e) => field('title', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.preferredName')} error={errors.preferred_name}>
                    <input
                        className="rp-form-input"
                        value={data.preferred_name}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.preferredName')}
                        onChange={(e) => field('preferred_name', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.firstName')} error={errors.first_name} required>
                    <input
                        className="rp-form-input"
                        value={data.first_name}
                        disabled={readOnly}
                        required={!readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.firstName')}
                        onChange={(e) => field('first_name', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.middleName')} error={errors.middle_name}>
                    <input
                        className="rp-form-input"
                        value={data.middle_name}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.middleName')}
                        onChange={(e) => field('middle_name', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.lastName')} error={errors.last_name} required>
                    <input
                        className="rp-form-input"
                        value={data.last_name}
                        disabled={readOnly}
                        required={!readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.lastName')}
                        onChange={(e) => field('last_name', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.gender')} error={errors.gender}>
                    <Select
                        value={data.gender}
                        isDisabled={readOnly}
                        options={[
                            { value: '', label: t('common.none') },
                            ...genders.map((g) => ({
                                value: g,
                                label: t(`pages.hrEmployees.genders.${g}`),
                            })),
                        ]}
                        onChange={(v) => field('gender', v ?? '')}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.dateOfBirth')} error={errors.date_of_birth}>
                    <input
                        type="date"
                        className="rp-form-input"
                        value={data.date_of_birth}
                        disabled={readOnly}
                        onChange={(e) => field('date_of_birth', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.maritalStatus')} error={errors.marital_status}>
                    <Select
                        value={data.marital_status}
                        isDisabled={readOnly}
                        options={[
                            { value: '', label: t('common.none') },
                            ...maritalStatuses.map((s) => ({
                                value: s,
                                label: t(`pages.hrEmployees.maritalStatuses.${s}`),
                            })),
                        ]}
                        onChange={(v) => field('marital_status', v ?? '')}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.nationality')} error={errors.nationality}>
                    <input
                        className="rp-form-input"
                        value={data.nationality}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.nationality')}
                        onChange={(e) => field('nationality', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.nationalId')} error={errors.national_id}>
                    <input
                        className="rp-form-input"
                        value={data.national_id}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.nationalId')}
                        onChange={(e) => field('national_id', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.email')} error={errors.email}>
                    <input
                        type="email"
                        className="rp-form-input"
                        value={data.email}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.email')}
                        onChange={(e) => field('email', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.phone')} error={errors.phone}>
                    <input
                        className="rp-form-input"
                        value={data.phone}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.phone')}
                        onChange={(e) => field('phone', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField
                    label={t('pages.hrEmployees.fields.addressLine1')}
                    error={errors['profile.address_line1']}
                    className="sm:col-span-2"
                >
                    <input
                        className="rp-form-input"
                        value={data.profile?.address_line1 ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.addressLine1')}
                        onChange={(e) =>
                            setData('profile', { ...data.profile, address_line1: e.target.value })
                        }
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.addressLine2')} className="sm:col-span-2">
                    <input
                        className="rp-form-input"
                        value={data.profile?.address_line2 ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.addressLine2')}
                        onChange={(e) =>
                            setData('profile', { ...data.profile, address_line2: e.target.value })
                        }
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.city')}>
                    <input
                        className="rp-form-input"
                        value={data.profile?.city ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.city')}
                        onChange={(e) => setData('profile', { ...data.profile, city: e.target.value })}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.state')}>
                    <input
                        className="rp-form-input"
                        value={data.profile?.state ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.state')}
                        onChange={(e) => setData('profile', { ...data.profile, state: e.target.value })}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.postalCode')}>
                    <input
                        className="rp-form-input"
                        value={data.profile?.postal_code ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.postalCode')}
                        onChange={(e) =>
                            setData('profile', { ...data.profile, postal_code: e.target.value })
                        }
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.country')}>
                    <input
                        className="rp-form-input"
                        value={data.profile?.country ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.country')}
                        onChange={(e) => setData('profile', { ...data.profile, country: e.target.value })}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.emergencyContactName')}>
                    <input
                        className="rp-form-input"
                        value={data.profile?.emergency_contact_name ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.emergencyContactName')}
                        onChange={(e) =>
                            setData('profile', {
                                ...data.profile,
                                emergency_contact_name: e.target.value,
                            })
                        }
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.emergencyContactPhone')}>
                    <input
                        className="rp-form-input"
                        value={data.profile?.emergency_contact_phone ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.emergencyContactPhone')}
                        onChange={(e) =>
                            setData('profile', {
                                ...data.profile,
                                emergency_contact_phone: e.target.value,
                            })
                        }
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.emergencyContactRelation')}>
                    <input
                        className="rp-form-input"
                        value={data.profile?.emergency_contact_relation ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.emergencyContactRelation')}
                        onChange={(e) =>
                            setData('profile', {
                                ...data.profile,
                                emergency_contact_relation: e.target.value,
                            })
                        }
                    />
                </AdminFormField>
            </div>
        );
    }

    if (section === 'service') {
        return (
            <div className="grid gap-4 sm:grid-cols-2">
                <AdminFormField
                    label={t('pages.hrEmployees.fields.employmentType')}
                    error={errors.employment_type}
                    required
                >
                    <Select
                        value={data.employment_type}
                        isDisabled={readOnly}
                        options={employmentTypes.map((type) => ({
                            value: type,
                            label: t(`pages.hrEmployees.employmentTypes.${type}`),
                        }))}
                        onChange={(v) => field('employment_type', v ?? 'full_time')}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.joinedAs')} error={errors.joined_as}>
                    <input
                        className="rp-form-input"
                        value={data.joined_as}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.joinedAs')}
                        onChange={(e) => field('joined_as', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.hireDate')} error={errors.hire_date} required>
                    <input
                        type="date"
                        className="rp-form-input"
                        value={data.hire_date}
                        disabled={readOnly}
                        required={!readOnly}
                        onChange={(e) => field('hire_date', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField
                    label={t('pages.hrEmployees.fields.probationEndDate')}
                    error={errors.probation_end_date}
                >
                    <input
                        type="date"
                        className="rp-form-input"
                        value={data.probation_end_date}
                        disabled={readOnly}
                        onChange={(e) => field('probation_end_date', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField
                    label={t('pages.hrEmployees.fields.confirmationDate')}
                    error={errors.confirmation_date}
                >
                    <input
                        type="date"
                        className="rp-form-input"
                        value={data.confirmation_date}
                        disabled={readOnly}
                        onChange={(e) => field('confirmation_date', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField
                    label={t('pages.hrEmployees.fields.contractEndDate')}
                    error={errors.contract_end_date}
                >
                    <input
                        type="date"
                        className="rp-form-input"
                        value={data.contract_end_date}
                        disabled={readOnly}
                        onChange={(e) => field('contract_end_date', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField
                    label={t('pages.hrEmployees.fields.terminationDate')}
                    error={errors.termination_date}
                >
                    <input
                        type="date"
                        className="rp-form-input"
                        value={data.termination_date}
                        disabled={readOnly}
                        onChange={(e) => field('termination_date', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.status')} error={errors.status}>
                    <Select
                        value={data.status}
                        isDisabled={readOnly}
                        options={['active', 'inactive', 'terminated'].map((s) => ({
                            value: s,
                            label: t(`pages.hrEmployees.statuses.${s}`),
                        }))}
                        onChange={(v) => field('status', v ?? 'active')}
                    />
                </AdminFormField>
                <AdminFormField
                    label={t('pages.hrEmployees.fields.salaryStructure')}
                    error={errors.salary_structure_id}
                >
                    <Select
                        value={data.salary_structure_id}
                        isDisabled={readOnly}
                        options={structureOptions}
                        onChange={(v) => field('salary_structure_id', v ?? '')}
                        isClearable
                    />
                </AdminFormField>
            </div>
        );
    }

    if (section === 'company') {
        return (
            <div className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <AdminFormField
                        label={t('pages.hrEmployees.fields.legalEntity')}
                        error={errors.legal_entity_id}
                        required
                    >
                        <Select
                            value={String(data.legal_entity_id)}
                            isDisabled={readOnly}
                            options={entityOptions}
                            onChange={(v) => field('legal_entity_id', v ?? '')}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.hrEmployees.fields.branch')}
                        error={errors.primary_branch_id}
                        required
                    >
                        <Select
                            value={String(data.primary_branch_id)}
                            isDisabled={readOnly}
                            options={branchOptions}
                            onChange={(v) => field('primary_branch_id', v ?? '')}
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.hrEmployees.fields.department')} error={errors.department_id}>
                        <Select
                            value={String(data.department_id ?? '')}
                            isDisabled={readOnly}
                            options={departmentOptions}
                            onChange={(v) => field('department_id', v ?? '')}
                            isClearable
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.hrEmployees.fields.designation')}
                        error={errors.designation_id}
                    >
                        <Select
                            value={String(data.designation_id ?? '')}
                            isDisabled={readOnly}
                            options={designationOptions}
                            onChange={(v) => field('designation_id', v ?? '')}
                            isClearable
                        />
                    </AdminFormField>
                    <AdminFormField label={t('pages.hrEmployees.fields.grade')} error={errors.grade_id}>
                        <Select
                            value={String(data.grade_id ?? '')}
                            isDisabled={readOnly}
                            options={gradeOptions}
                            onChange={(v) => field('grade_id', v ?? '')}
                            isClearable
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.hrEmployees.fields.reportingManager')}
                        error={errors.reporting_manager_employee_id}
                    >
                        <Select
                            value={String(data.reporting_manager_employee_id ?? '')}
                            isDisabled={readOnly}
                            options={managerOptions}
                            onChange={(v) => field('reporting_manager_employee_id', v ?? '')}
                            isClearable
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.hrEmployees.fields.costCentre')}
                        error={errors.default_cost_centre_id}
                    >
                        <Select
                            value={String(data.default_cost_centre_id ?? '')}
                            isDisabled={readOnly}
                            options={costCentreOptions}
                            onChange={(v) => field('default_cost_centre_id', v ?? '')}
                            isClearable
                        />
                    </AdminFormField>
                </div>
                {!hideSecondaryBranches && (
                    <div>
                        <div className="mb-2 flex items-center justify-between">
                            <h4 className="text-sm font-semibold">{t('pages.hrEmployees.fields.secondaryBranches')}</h4>
                            {!readOnly && (
                                <Button type="button" variant="outline" size="sm" onClick={addBranchAssignment}>
                                    <Plus className="h-4 w-4" />
                                    {t('pages.hrEmployees.addBranchAssignment')}
                                </Button>
                            )}
                        </div>
                        <div className="space-y-3">
                            {(data.branch_assignments ?? []).map((row, index) => (
                                <div key={row.id ?? index} className="grid gap-2 rounded-lg border border-rp-border p-3 sm:grid-cols-4">
                                    <Select
                                        value={String(row.branch_id)}
                                        isDisabled={readOnly}
                                        options={branchOptions}
                                        onChange={(v) => {
                                            const next = [...data.branch_assignments];
                                            next[index] = { ...next[index], branch_id: v ?? '' };
                                            setData('branch_assignments', next);
                                        }}
                                    />
                                    <input
                                        type="date"
                                        className="rp-form-input"
                                        value={row.effective_from}
                                        disabled={readOnly}
                                        onChange={(e) => {
                                            const next = [...data.branch_assignments];
                                            next[index] = { ...next[index], effective_from: e.target.value };
                                            setData('branch_assignments', next);
                                        }}
                                    />
                                    <input
                                        type="date"
                                        className="rp-form-input"
                                        value={row.effective_to}
                                        disabled={readOnly}
                                        onChange={(e) => {
                                            const next = [...data.branch_assignments];
                                            next[index] = { ...next[index], effective_to: e.target.value };
                                            setData('branch_assignments', next);
                                        }}
                                    />
                                    {!readOnly && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() =>
                                                setData(
                                                    'branch_assignments',
                                                    data.branch_assignments.filter((_, i) => i !== index),
                                                )
                                            }
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        );
    }

    if (section === 'dependents') {
        return (
            <div className="space-y-3">
                {!readOnly && (
                    <Button type="button" variant="outline" onClick={addDependent}>
                        <Plus className="h-4 w-4" />
                        {t('pages.hrEmployees.addDependent')}
                    </Button>
                )}
                {(data.dependents ?? []).length === 0 && (
                    <p className="text-sm text-rp-text-muted">{t('pages.hrEmployees.emptyDependents')}</p>
                )}
                {(data.dependents ?? []).map((row, index) => (
                    <div key={row.id ?? index} className="grid gap-3 rounded-lg border border-rp-border p-3 sm:grid-cols-2">
                        <AdminFormField label={t('pages.hrEmployees.fields.dependentName')}>
                            <input
                                className="rp-form-input"
                                value={row.name}
                                disabled={readOnly}
                                placeholder={t('pages.hrEmployees.placeholders.dependentName')}
                                onChange={(e) => {
                                    const next = [...data.dependents];
                                    next[index] = { ...next[index], name: e.target.value };
                                    setData('dependents', next);
                                }}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.hrEmployees.fields.relation')}>
                            <input
                                className="rp-form-input"
                                value={row.relation}
                                disabled={readOnly}
                                placeholder={t('pages.hrEmployees.placeholders.relation')}
                                onChange={(e) => {
                                    const next = [...data.dependents];
                                    next[index] = { ...next[index], relation: e.target.value };
                                    setData('dependents', next);
                                }}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.hrEmployees.fields.dateOfBirth')}>
                            <input
                                type="date"
                                className="rp-form-input"
                                value={row.date_of_birth}
                                disabled={readOnly}
                                onChange={(e) => {
                                    const next = [...data.dependents];
                                    next[index] = { ...next[index], date_of_birth: e.target.value };
                                    setData('dependents', next);
                                }}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.hrEmployees.fields.phone')}>
                            <input
                                className="rp-form-input"
                                value={row.phone}
                                disabled={readOnly}
                                placeholder={t('pages.hrEmployees.placeholders.phone')}
                                onChange={(e) => {
                                    const next = [...data.dependents];
                                    next[index] = { ...next[index], phone: e.target.value };
                                    setData('dependents', next);
                                }}
                            />
                        </AdminFormField>
                        <label className="flex items-center gap-2 text-sm sm:col-span-2">
                            <input
                                type="checkbox"
                                checked={!!row.is_emergency_contact}
                                disabled={readOnly}
                                onChange={(e) => {
                                    const next = [...data.dependents];
                                    next[index] = {
                                        ...next[index],
                                        is_emergency_contact: e.target.checked,
                                    };
                                    setData('dependents', next);
                                }}
                            />
                            {t('pages.hrEmployees.fields.isEmergencyContact')}
                        </label>
                        {!readOnly && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    setData(
                                        'dependents',
                                        data.dependents.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                <Trash2 className="h-4 w-4" />
                                {t('common.delete')}
                            </Button>
                        )}
                    </div>
                ))}
            </div>
        );
    }

    if (section === 'shifts') {
        return (
            <div className="grid gap-4 sm:grid-cols-2">
                <p className="sm:col-span-2 text-sm text-rp-text-muted">
                    {t('pages.hrEmployees.shiftHint')}
                </p>
                <AdminFormField label={t('pages.hrEmployees.fields.shiftLabel')}>
                    <input
                        className="rp-form-input"
                        value={data.shift?.shift_label ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.shiftLabel')}
                        onChange={(e) => setData('shift', { ...data.shift, shift_label: e.target.value })}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.startTime')}>
                    <input
                        type="time"
                        className="rp-form-input"
                        value={data.shift?.start_time ?? ''}
                        disabled={readOnly}
                        onChange={(e) => setData('shift', { ...data.shift, start_time: e.target.value })}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.endTime')}>
                    <input
                        type="time"
                        className="rp-form-input"
                        value={data.shift?.end_time ?? ''}
                        disabled={readOnly}
                        onChange={(e) => setData('shift', { ...data.shift, end_time: e.target.value })}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.restDays')} className="sm:col-span-2">
                    <div className="flex flex-wrap gap-2">
                        {weekDays.map((day) => {
                            const selected = (data.shift?.rest_days ?? []).includes(day);
                            return (
                                <label key={day} className="inline-flex items-center gap-1 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={selected}
                                        disabled={readOnly}
                                        onChange={(e) => {
                                            const current = data.shift?.rest_days ?? [];
                                            setData('shift', {
                                                ...data.shift,
                                                rest_days: e.target.checked
                                                    ? [...current, day]
                                                    : current.filter((d) => d !== day),
                                            });
                                        }}
                                    />
                                    {t(`pages.hrEmployees.weekDays.${day}`)}
                                </label>
                            );
                        })}
                    </div>
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.notes')} className="sm:col-span-2">
                    <textarea
                        className="rp-form-input min-h-24"
                        value={data.shift?.notes ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.notes')}
                        onChange={(e) => setData('shift', { ...data.shift, notes: e.target.value })}
                    />
                </AdminFormField>
            </div>
        );
    }

    if (section === 'holidays') {
        return (
            <div className="space-y-4">
                <AdminFormField
                    label={t('pages.hrEmployees.fields.holidayCalendar')}
                    error={errors.holiday_calendar_id}
                >
                    <Select
                        value={String(data.holiday_calendar_id ?? '')}
                        isDisabled={readOnly}
                        options={calendarOptions}
                        onChange={(v) => field('holiday_calendar_id', v ?? '')}
                        isClearable
                    />
                </AdminFormField>
                {(employee?.holiday_assignments ?? []).length > 0 && (
                    <ul className="space-y-1 text-sm text-rp-text-muted">
                        {employee.holiday_assignments.map((a) => (
                            <li key={a.id}>
                                {a.calendar_name} ({a.effective_from}
                                {a.effective_to ? ` → ${a.effective_to}` : ''})
                            </li>
                        ))}
                    </ul>
                )}
                <p className="text-sm text-rp-text-muted">{t('pages.hrEmployees.holidayHint')}</p>
            </div>
        );
    }

    if (section === 'attendance') {
        return (
            <div className="grid gap-4 sm:grid-cols-2">
                <AdminFormField label={t('pages.hrEmployees.fields.attendanceGraceMinutes')}>
                    <input
                        type="number"
                        min="0"
                        className="rp-form-input"
                        value={data.profile?.attendance_grace_minutes ?? 0}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.attendanceGraceMinutes')}
                        onChange={(e) =>
                            setData('profile', {
                                ...data.profile,
                                attendance_grace_minutes: Number(e.target.value),
                            })
                        }
                    />
                </AdminFormField>
                <label className="flex items-center gap-2 self-end text-sm">
                    <input
                        type="checkbox"
                        checked={!!data.profile?.overtime_eligible}
                        disabled={readOnly}
                        onChange={(e) =>
                            setData('profile', {
                                ...data.profile,
                                overtime_eligible: e.target.checked,
                            })
                        }
                    />
                    {t('pages.hrEmployees.fields.overtimeEligible')}
                </label>
                <p className="sm:col-span-2 text-sm text-rp-text-muted">
                    {t('pages.hrEmployees.attendanceHint')}{' '}
                    <Link href={route('admin.attendance.records.index')} className="text-teal-600 underline">
                        {t('pages.hrEmployees.openAttendance')}
                    </Link>
                </p>
            </div>
        );
    }

    if (section === 'attachments') {
        return (
            <EmployeeImageAttachments
                batches={data.image_batches ?? []}
                onBatchesChange={(batches) => setData('image_batches', batches)}
                attachmentTypes={attachmentTypes}
                existingImages={employee?.images ?? []}
                removeImageIds={data.remove_image_ids ?? []}
                onRemoveImageIdsChange={(ids) => setData('remove_image_ids', ids)}
                maxImages={maxImages}
                disabled={readOnly}
                errors={errors}
            />
        );
    }

    if (section === 'medical') {
        return (
            <div className="grid gap-4 sm:grid-cols-2">
                <AdminFormField label={t('pages.hrEmployees.fields.bloodGroup')}>
                    <input
                        className="rp-form-input"
                        value={data.medical?.blood_group ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.bloodGroup')}
                        onChange={(e) => setData('medical', { ...data.medical, blood_group: e.target.value })}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.insuranceProvider')}>
                    <input
                        className="rp-form-input"
                        value={data.medical?.insurance_provider ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.insuranceProvider')}
                        onChange={(e) =>
                            setData('medical', { ...data.medical, insurance_provider: e.target.value })
                        }
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.insurancePolicyNo')}>
                    <input
                        className="rp-form-input"
                        value={data.medical?.insurance_policy_no ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.insurancePolicyNo')}
                        onChange={(e) =>
                            setData('medical', { ...data.medical, insurance_policy_no: e.target.value })
                        }
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.allergies')} className="sm:col-span-2">
                    <textarea
                        className="rp-form-input min-h-20"
                        value={data.medical?.allergies ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.allergies')}
                        onChange={(e) => setData('medical', { ...data.medical, allergies: e.target.value })}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.conditions')} className="sm:col-span-2">
                    <textarea
                        className="rp-form-input min-h-20"
                        value={data.medical?.conditions ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.conditions')}
                        onChange={(e) => setData('medical', { ...data.medical, conditions: e.target.value })}
                    />
                </AdminFormField>
                <AdminFormField label={t('pages.hrEmployees.fields.emergencyNotes')} className="sm:col-span-2">
                    <textarea
                        className="rp-form-input min-h-20"
                        value={data.medical?.emergency_notes ?? ''}
                        disabled={readOnly}
                        placeholder={t('pages.hrEmployees.placeholders.emergencyNotes')}
                        onChange={(e) =>
                            setData('medical', { ...data.medical, emergency_notes: e.target.value })
                        }
                    />
                </AdminFormField>
            </div>
        );
    }

    if (section === 'banks') {
        return (
            <div className="space-y-3">
                {showBanksOptionalHint && (
                    <p className="text-sm text-rp-text-muted">{t('pages.hrEmployees.wizard.banksOptionalHint')}</p>
                )}
                <AdminFormField label={t('pages.hrEmployees.fields.paymentMethod')} error={errors.payment_method}>
                    <Select
                        value={data.payment_method || ''}
                        isDisabled={readOnly}
                        options={[
                            { value: '', label: t('common.none') },
                            ...['bank_transfer', 'cash', 'cheque', 'mobile_wallet'].map((method) => ({
                                value: method,
                                label: t(`common.paymentMethods.${method}`, {
                                    defaultValue: method,
                                }),
                            })),
                        ]}
                        onChange={(v) => field('payment_method', v ?? '')}
                    />
                </AdminFormField>
                {!readOnly && (
                    <Button type="button" variant="outline" onClick={addBank}>
                        <Plus className="h-4 w-4" />
                        {t('pages.hrEmployees.addBankAccount')}
                    </Button>
                )}
                {(data.bank_accounts ?? []).length === 0 && (
                    <p className="text-sm text-rp-text-muted">{t('pages.hrEmployees.emptyBanks')}</p>
                )}
                {(data.bank_accounts ?? []).map((row, index) => (
                    <div key={row.id ?? index} className="grid gap-3 rounded-lg border border-rp-border p-3 sm:grid-cols-2">
                        <AdminFormField label={t('pages.hrEmployees.fields.label')}>
                            <input
                                className="rp-form-input"
                                value={row.label}
                                disabled={readOnly}
                                placeholder={t('pages.hrEmployees.placeholders.label')}
                                onChange={(e) => {
                                    const next = [...data.bank_accounts];
                                    next[index] = { ...next[index], label: e.target.value };
                                    setData('bank_accounts', next);
                                }}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.hrEmployees.fields.bankName')}>
                            <input
                                className="rp-form-input"
                                value={row.bank_name}
                                disabled={readOnly}
                                placeholder={t('pages.hrEmployees.placeholders.bankName')}
                                onChange={(e) => {
                                    const next = [...data.bank_accounts];
                                    next[index] = { ...next[index], bank_name: e.target.value };
                                    setData('bank_accounts', next);
                                }}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.hrEmployees.fields.accountNumber')}>
                            <input
                                className="rp-form-input"
                                value={row.account_number}
                                disabled={readOnly}
                                placeholder={t('pages.hrEmployees.placeholders.accountNumber')}
                                onChange={(e) => {
                                    const next = [...data.bank_accounts];
                                    next[index] = { ...next[index], account_number: e.target.value };
                                    setData('bank_accounts', next);
                                }}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.hrEmployees.fields.iban')}>
                            <input
                                className="rp-form-input"
                                value={row.iban}
                                disabled={readOnly}
                                placeholder={t('pages.hrEmployees.placeholders.iban')}
                                onChange={(e) => {
                                    const next = [...data.bank_accounts];
                                    next[index] = { ...next[index], iban: e.target.value };
                                    setData('bank_accounts', next);
                                }}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.hrEmployees.fields.currency')}>
                            <Select
                                value={row.currency_code}
                                isDisabled={readOnly}
                                options={currencyOptions}
                                onChange={(v) => {
                                    const next = [...data.bank_accounts];
                                    next[index] = { ...next[index], currency_code: v ?? '' };
                                    setData('bank_accounts', next);
                                }}
                            />
                        </AdminFormField>
                        <label className="flex items-center gap-2 text-sm sm:col-span-2">
                            <input
                                type="checkbox"
                                checked={!!row.is_primary}
                                disabled={readOnly}
                                onChange={(e) => {
                                    const next = data.bank_accounts.map((b, i) => ({
                                        ...b,
                                        is_primary: i === index ? e.target.checked : false,
                                    }));
                                    setData('bank_accounts', next);
                                }}
                            />
                            {t('pages.hrEmployees.fields.isPrimary')}
                        </label>
                        {!readOnly && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    setData(
                                        'bank_accounts',
                                        data.bank_accounts.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                <Trash2 className="h-4 w-4" />
                                {t('common.delete')}
                            </Button>
                        )}
                    </div>
                ))}
            </div>
        );
    }

    return null;
}
