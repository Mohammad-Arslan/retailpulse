import {
    Briefcase,
    Building2,
    CalendarDays,
    ClipboardList,
    HeartPulse,
    Landmark,
    Paperclip,
    Timer,
    User,
    Users,
} from 'lucide-react';

export const EMPLOYEE_TABS = [
    { id: 'basic', icon: User, labelKey: 'basic' },
    { id: 'service', icon: Briefcase, labelKey: 'service' },
    { id: 'company', icon: Building2, labelKey: 'company' },
    { id: 'dependents', icon: Users, labelKey: 'dependents' },
    { id: 'shifts', icon: ClipboardList, labelKey: 'shifts' },
    { id: 'holidays', icon: CalendarDays, labelKey: 'holidays' },
    { id: 'attendance', icon: Timer, labelKey: 'attendance' },
    { id: 'attachments', icon: Paperclip, labelKey: 'attachments' },
    { id: 'medical', icon: HeartPulse, labelKey: 'medical' },
    { id: 'banks', icon: Landmark, labelKey: 'banks' },
];

/** Create wizard: progressive disclosure of core sections only. */
export const CREATE_WIZARD_STEPS = [
    { id: 'basic', labelKey: 'basic' },
    { id: 'service', labelKey: 'service' },
    { id: 'company', labelKey: 'company' },
    { id: 'banks', labelKey: 'banks' },
];

export const WIZARD_STEP_REQUIRED = {
    basic: ['first_name', 'last_name'],
    service: ['employment_type', 'hire_date'],
    company: ['legal_entity_id', 'primary_branch_id'],
    banks: [],
};

export function validateWizardStep(stepId, data) {
    const required = WIZARD_STEP_REQUIRED[stepId] ?? [];
    const errors = {};

    for (const field of required) {
        const value = data?.[field];
        if (value === null || value === undefined || String(value).trim() === '') {
            errors[field] = 'required';
        }
    }

    return errors;
}

export const TAB_ERROR_PREFIXES = {
    basic: [
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'preferred_name',
        'gender',
        'date_of_birth',
        'marital_status',
        'nationality',
        'national_id',
        'email',
        'phone',
        'profile.address',
        'profile.city',
        'profile.state',
        'profile.postal',
        'profile.country',
        'profile.emergency',
    ],
    service: [
        'employment_type',
        'hire_date',
        'termination_date',
        'probation_end_date',
        'confirmation_date',
        'contract_end_date',
        'joined_as',
        'status',
        'salary_structure_id',
    ],
    company: [
        'legal_entity_id',
        'primary_branch_id',
        'department_id',
        'designation_id',
        'grade_id',
        'reporting_manager_employee_id',
        'default_cost_centre_id',
        'branch_assignments',
    ],
    dependents: ['dependents'],
    shifts: ['shift'],
    holidays: ['holiday_calendar_id'],
    attendance: ['profile.attendance', 'profile.overtime'],
    attachments: ['image_batches', 'image_uploads', 'remove_image_ids', 'images', 'cnic_front', 'cnic_back'],
    medical: ['medical'],
    banks: ['bank_accounts', 'payment_method'],
};

function tabOwnsError(tabId, key) {
    const prefixes = TAB_ERROR_PREFIXES[tabId] ?? [];

    return prefixes.some(
        (prefix) => key === prefix || key.startsWith(`${prefix}.`) || key.startsWith(`${prefix}[`),
    );
}

export function firstTabWithErrors(errors = {}) {
    const keys = Object.keys(errors);
    if (keys.length === 0) {
        return null;
    }

    for (const tab of EMPLOYEE_TABS) {
        if (keys.some((key) => tabOwnsError(tab.id, key))) {
            return tab.id;
        }
    }

    return EMPLOYEE_TABS[0].id;
}

export function tabsWithErrors(errors = {}) {
    const keys = Object.keys(errors);
    if (keys.length === 0) {
        return [];
    }

    return EMPLOYEE_TABS.filter((tab) => keys.some((key) => tabOwnsError(tab.id, key))).map((tab) => tab.id);
}

export function firstWizardStepWithErrors(errors = {}) {
    const keys = Object.keys(errors);
    if (keys.length === 0) {
        return null;
    }

    for (const step of CREATE_WIZARD_STEPS) {
        if (keys.some((key) => tabOwnsError(step.id, key))) {
            return step.id;
        }
    }

    return null;
}

export function prepareEmployeeFormPayload(data) {
    const payload = { ...data };
    delete payload._currency_default;
    delete payload.attachment;
    delete payload.attachment_type;
    delete payload.pending_images;
    delete payload.cnic_front;
    delete payload.cnic_back;
    delete payload.image_batches;

    payload.profile = {
        ...(payload.profile ?? {}),
        attendance_grace_minutes: Number(payload.profile?.attendance_grace_minutes ?? 0),
        overtime_eligible: payload.profile?.overtime_eligible ? 1 : 0,
    };

    payload.dependents = (payload.dependents ?? []).map((row) => ({
        ...row,
        is_emergency_contact: row.is_emergency_contact ? 1 : 0,
    }));

    payload.bank_accounts = (payload.bank_accounts ?? []).map((row) => ({
        ...row,
        is_primary: row.is_primary ? 1 : 0,
    }));

    const imageUploads = (data.image_batches ?? [])
        .map((batch) => {
            if (batch.type === 'cnic') {
                const row = { type: 'cnic' };
                if (batch.cnic_front?.file) {
                    row.cnic_front = batch.cnic_front.file;
                }
                if (batch.cnic_back?.file) {
                    row.cnic_back = batch.cnic_back.file;
                }
                if (!row.cnic_front && !row.cnic_back) {
                    return null;
                }
                return row;
            }

            const files = (batch.pending_images ?? []).map((item) => item.file).filter(Boolean);
            if (files.length === 0) {
                return null;
            }

            return {
                type: batch.type || 'other',
                images: files,
            };
        })
        .filter(Boolean);

    if (imageUploads.length > 0) {
        payload.image_uploads = imageUploads;
    } else {
        delete payload.image_uploads;
        delete payload.images;
    }

    if (!payload.remove_image_ids?.length) {
        delete payload.remove_image_ids;
    }

    return payload;
}

export function emptyEmployeeForm(options = {}) {
    const {
        legalEntities = [],
        branches = [],
        currencies = [],
    } = options;

    return {
        title: '',
        first_name: '',
        middle_name: '',
        last_name: '',
        preferred_name: '',
        gender: '',
        date_of_birth: '',
        marital_status: '',
        nationality: '',
        national_id: '',
        email: '',
        phone: '',
        legal_entity_id: legalEntities[0]?.id ? String(legalEntities[0].id) : '',
        primary_branch_id: branches[0]?.id ? String(branches[0].id) : '',
        department_id: '',
        designation_id: '',
        grade_id: '',
        reporting_manager_employee_id: '',
        salary_structure_id: '',
        hire_date: new Date().toISOString().slice(0, 10),
        termination_date: '',
        probation_end_date: '',
        confirmation_date: '',
        contract_end_date: '',
        employment_type: 'full_time',
        joined_as: '',
        default_cost_centre_id: '',
        payment_method: '',
        status: 'active',
        profile: {
            address_line1: '',
            address_line2: '',
            city: '',
            state: '',
            postal_code: '',
            country: '',
            emergency_contact_name: '',
            emergency_contact_phone: '',
            emergency_contact_relation: '',
            attendance_grace_minutes: 0,
            overtime_eligible: true,
        },
        shift: {
            shift_label: '',
            start_time: '',
            end_time: '',
            rest_days: [],
            notes: '',
        },
        medical: {
            blood_group: '',
            allergies: '',
            conditions: '',
            insurance_provider: '',
            insurance_policy_no: '',
            emergency_notes: '',
        },
        dependents: [],
        bank_accounts: [],
        branch_assignments: [],
        holiday_calendar_id: '',
        image_batches: [],
        remove_image_ids: [],
        active_tab: 'basic',
        _currency_default: currencies[0]?.code ?? '',
    };
}

export function employeeToForm(employee) {
    const holidays = employee.holiday_assignments ?? [];
    return {
        title: employee.title ?? '',
        first_name: employee.first_name ?? '',
        middle_name: employee.middle_name ?? '',
        last_name: employee.last_name ?? '',
        preferred_name: employee.preferred_name ?? '',
        gender: employee.gender ?? '',
        date_of_birth: employee.date_of_birth ?? '',
        marital_status: employee.marital_status ?? '',
        nationality: employee.nationality ?? '',
        national_id: employee.national_id ?? '',
        email: employee.email ?? '',
        phone: employee.phone ?? '',
        legal_entity_id: employee.legal_entity_id ? String(employee.legal_entity_id) : '',
        primary_branch_id: employee.primary_branch_id ? String(employee.primary_branch_id) : '',
        department_id: employee.department_id ? String(employee.department_id) : '',
        designation_id: employee.designation_id ? String(employee.designation_id) : '',
        grade_id: employee.grade_id ? String(employee.grade_id) : '',
        reporting_manager_employee_id: employee.reporting_manager_employee_id
            ? String(employee.reporting_manager_employee_id)
            : '',
        salary_structure_id: employee.salary_structure_id ? String(employee.salary_structure_id) : '',
        hire_date: employee.hire_date ?? '',
        termination_date: employee.termination_date ?? '',
        probation_end_date: employee.probation_end_date ?? '',
        confirmation_date: employee.confirmation_date ?? '',
        contract_end_date: employee.contract_end_date ?? '',
        employment_type: employee.employment_type ?? 'full_time',
        joined_as: employee.joined_as ?? '',
        default_cost_centre_id: employee.default_cost_centre_id ? String(employee.default_cost_centre_id) : '',
        payment_method: employee.payment_method ?? '',
        status: employee.status ?? 'active',
        profile: {
            address_line1: employee.profile?.address_line1 ?? '',
            address_line2: employee.profile?.address_line2 ?? '',
            city: employee.profile?.city ?? '',
            state: employee.profile?.state ?? '',
            postal_code: employee.profile?.postal_code ?? '',
            country: employee.profile?.country ?? '',
            emergency_contact_name: employee.profile?.emergency_contact_name ?? '',
            emergency_contact_phone: employee.profile?.emergency_contact_phone ?? '',
            emergency_contact_relation: employee.profile?.emergency_contact_relation ?? '',
            attendance_grace_minutes: employee.profile?.attendance_grace_minutes ?? 0,
            overtime_eligible: employee.profile?.overtime_eligible ?? true,
        },
        shift: {
            shift_label: employee.shift?.shift_label ?? '',
            start_time: employee.shift?.start_time ?? '',
            end_time: employee.shift?.end_time ?? '',
            rest_days: employee.shift?.rest_days ?? [],
            notes: employee.shift?.notes ?? '',
        },
        medical: {
            blood_group: employee.medical?.blood_group ?? '',
            allergies: employee.medical?.allergies ?? '',
            conditions: employee.medical?.conditions ?? '',
            insurance_provider: employee.medical?.insurance_provider ?? '',
            insurance_policy_no: employee.medical?.insurance_policy_no ?? '',
            emergency_notes: employee.medical?.emergency_notes ?? '',
        },
        dependents: (employee.dependents ?? []).map((d) => ({
            id: d.id,
            name: d.name ?? '',
            relation: d.relation ?? '',
            date_of_birth: d.date_of_birth ?? '',
            gender: d.gender ?? '',
            national_id: d.national_id ?? '',
            phone: d.phone ?? '',
            is_emergency_contact: !!d.is_emergency_contact,
        })),
        bank_accounts: (employee.bank_accounts ?? []).map((b) => ({
            id: b.id,
            label: b.label ?? '',
            bank_name: b.bank_name ?? '',
            account_number: b.account_number ?? '',
            iban: b.iban ?? '',
            currency_code: b.currency_code ?? '',
            payment_method: b.payment_method ?? '',
            is_primary: !!b.is_primary,
        })),
        branch_assignments: (employee.branch_assignments ?? []).map((a) => ({
            id: a.id,
            branch_id: a.branch_id ? String(a.branch_id) : '',
            effective_from: a.effective_from ?? '',
            effective_to: a.effective_to ?? '',
            status: a.status ?? 'active',
        })),
        holiday_calendar_id: holidays[0]?.calendar_id ? String(holidays[0].calendar_id) : '',
        image_batches: [],
        remove_image_ids: [],
        active_tab: 'basic',
    };
}
