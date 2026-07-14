import { useTranslation } from 'react-i18next';

export default function EmployeeFormFields({
    data,
    setData,
    errors,
    legalEntities = [],
    branches = [],
    costCentres = [],
    employmentTypes = [],
}) {
    const { t } = useTranslation();

    const field = (name, label, input) => (
        <div>
            <label className="mb-1 block text-sm font-medium text-rp-text">{label}</label>
            {input}
            {errors[name] && <p className="mt-1 text-xs text-red-600">{errors[name]}</p>}
        </div>
    );

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {field(
                'first_name',
                t('pages.hrEmployees.fields.firstName'),
                <input
                    className="rp-input w-full"
                    value={data.first_name}
                    onChange={(e) => setData('first_name', e.target.value)}
                />,
            )}
            {field(
                'last_name',
                t('pages.hrEmployees.fields.lastName'),
                <input
                    className="rp-input w-full"
                    value={data.last_name}
                    onChange={(e) => setData('last_name', e.target.value)}
                />,
            )}
            {field(
                'email',
                t('pages.hrEmployees.fields.email'),
                <input
                    type="email"
                    className="rp-input w-full"
                    value={data.email ?? ''}
                    onChange={(e) => setData('email', e.target.value)}
                />,
            )}
            {field(
                'phone',
                t('pages.hrEmployees.fields.phone'),
                <input
                    className="rp-input w-full"
                    value={data.phone ?? ''}
                    onChange={(e) => setData('phone', e.target.value)}
                />,
            )}
            {field(
                'legal_entity_id',
                t('pages.hrEmployees.fields.legalEntity'),
                <select
                    className="rp-input w-full"
                    value={data.legal_entity_id}
                    onChange={(e) => setData('legal_entity_id', e.target.value)}
                >
                    <option value="">{t('pages.hrEmployees.selectLegalEntity')}</option>
                    {legalEntities.map((e) => (
                        <option key={e.id} value={e.id}>
                            {e.legal_name}
                        </option>
                    ))}
                </select>,
            )}
            {field(
                'primary_branch_id',
                t('pages.hrEmployees.fields.branch'),
                <select
                    className="rp-input w-full"
                    value={data.primary_branch_id}
                    onChange={(e) => setData('primary_branch_id', e.target.value)}
                >
                    <option value="">{t('pages.hrEmployees.selectBranch')}</option>
                    {branches.map((b) => (
                        <option key={b.id} value={b.id}>
                            {b.name}
                        </option>
                    ))}
                </select>,
            )}
            {field(
                'hire_date',
                t('pages.hrEmployees.fields.hireDate'),
                <input
                    type="date"
                    className="rp-input w-full"
                    value={data.hire_date}
                    onChange={(e) => setData('hire_date', e.target.value)}
                />,
            )}
            {field(
                'termination_date',
                t('pages.hrEmployees.fields.terminationDate'),
                <input
                    type="date"
                    className="rp-input w-full"
                    value={data.termination_date ?? ''}
                    onChange={(e) => setData('termination_date', e.target.value)}
                />,
            )}
            {field(
                'employment_type',
                t('pages.hrEmployees.fields.employmentType'),
                <select
                    className="rp-input w-full"
                    value={data.employment_type}
                    onChange={(e) => setData('employment_type', e.target.value)}
                >
                    {employmentTypes.map((type) => (
                        <option key={type} value={type}>
                            {t(`pages.hrEmployees.employmentTypes.${type}`)}
                        </option>
                    ))}
                </select>,
            )}
            {field(
                'default_cost_centre_id',
                t('pages.hrEmployees.fields.costCentre'),
                <select
                    className="rp-input w-full"
                    value={data.default_cost_centre_id ?? ''}
                    onChange={(e) => setData('default_cost_centre_id', e.target.value)}
                >
                    <option value="">{t('common.none')}</option>
                    {costCentres.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.code} — {c.name}
                        </option>
                    ))}
                </select>,
            )}
            {field(
                'status',
                t('pages.hrEmployees.fields.status'),
                <select
                    className="rp-input w-full"
                    value={data.status}
                    onChange={(e) => setData('status', e.target.value)}
                >
                    <option value="active">{t('pages.hrEmployees.statuses.active')}</option>
                    <option value="inactive">{t('pages.hrEmployees.statuses.inactive')}</option>
                    <option value="terminated">{t('pages.hrEmployees.statuses.terminated')}</option>
                </select>,
            )}
            {field(
                'payment_method',
                t('pages.hrEmployees.fields.paymentMethod'),
                <input
                    className="rp-input w-full"
                    value={data.payment_method ?? ''}
                    onChange={(e) => setData('payment_method', e.target.value)}
                />,
            )}
            <div className="sm:col-span-2">
                <p className="mb-2 text-sm font-medium text-rp-text">{t('pages.hrEmployees.fields.bankDetails')}</p>
                <div className="grid gap-4 sm:grid-cols-3">
                    <input
                        className="rp-input w-full"
                        placeholder={t('pages.hrEmployees.fields.bankName')}
                        value={data.bank_details_encrypted?.bank_name ?? ''}
                        onChange={(e) =>
                            setData('bank_details_encrypted', {
                                ...(data.bank_details_encrypted ?? {}),
                                bank_name: e.target.value,
                            })
                        }
                    />
                    <input
                        className="rp-input w-full"
                        placeholder={t('pages.hrEmployees.fields.accountNumber')}
                        value={data.bank_details_encrypted?.account_number ?? ''}
                        onChange={(e) =>
                            setData('bank_details_encrypted', {
                                ...(data.bank_details_encrypted ?? {}),
                                account_number: e.target.value,
                            })
                        }
                    />
                    <input
                        className="rp-input w-full"
                        placeholder={t('pages.hrEmployees.fields.iban')}
                        value={data.bank_details_encrypted?.iban ?? ''}
                        onChange={(e) =>
                            setData('bank_details_encrypted', {
                                ...(data.bank_details_encrypted ?? {}),
                                iban: e.target.value,
                            })
                        }
                    />
                </div>
            </div>
        </div>
    );
}
