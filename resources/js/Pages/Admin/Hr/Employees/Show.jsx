import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

function Show({ employee }) {
    const { t } = useTranslation();
    const can = useCan();

    const rows = [
        [t('pages.hrEmployees.fields.code'), employee.employee_code],
        [t('pages.hrEmployees.fields.name'), employee.name],
        [t('pages.hrEmployees.fields.email'), employee.email || '—'],
        [t('pages.hrEmployees.fields.phone'), employee.phone || '—'],
        [t('pages.hrEmployees.fields.legalEntity'), employee.legal_entity || '—'],
        [t('pages.hrEmployees.fields.branch'), employee.branch || '—'],
        [t('pages.hrEmployees.fields.costCentre'), employee.cost_centre || '—'],
        [t('pages.hrEmployees.fields.hireDate'), employee.hire_date || '—'],
        [t('pages.hrEmployees.fields.terminationDate'), employee.termination_date || '—'],
        [
            t('pages.hrEmployees.fields.employmentType'),
            t(`pages.hrEmployees.employmentTypes.${employee.employment_type}`, {
                defaultValue: employee.employment_type,
            }),
        ],
        [
            t('pages.hrEmployees.fields.status'),
            t(`pages.hrEmployees.statuses.${employee.status}`, { defaultValue: employee.status }),
        ],
        [t('pages.hrEmployees.fields.paymentMethod'), employee.payment_method || '—'],
        [t('pages.hrEmployees.fields.linkedUser'), employee.user_name || '—'],
    ];

    return (
        <>
            <Head title={employee.name} />
            <PageHeader title={employee.name} description={employee.employee_code}>
                <Link href={route('admin.hr.employees.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
                {can('hr.manage-employees') && (
                    <Link href={route('admin.hr.employees.edit', employee.id)} className="rp-btn-primary">
                        {t('common.edit')}
                    </Link>
                )}
            </PageHeader>

            <dl className="grid max-w-3xl gap-4 sm:grid-cols-2">
                {rows.map(([label, value]) => (
                    <div key={label} className="rounded-lg border border-rp-border p-3">
                        <dt className="text-xs text-rp-text-muted">{label}</dt>
                        <dd className="mt-1 text-sm font-medium text-rp-text">{value}</dd>
                    </div>
                ))}
            </dl>
        </>
    );
}

export default withAdminLayout(Show);
