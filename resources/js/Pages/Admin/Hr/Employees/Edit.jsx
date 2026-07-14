import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import EmployeeFormFields from './EmployeeFormFields';

function Edit({ employee, legalEntities = [], branches = [], costCentres = [], employmentTypes = [] }) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        first_name: employee.first_name ?? '',
        last_name: employee.last_name ?? '',
        email: employee.email ?? '',
        phone: employee.phone ?? '',
        legal_entity_id: employee.legal_entity_id ?? '',
        primary_branch_id: employee.primary_branch_id ?? '',
        hire_date: employee.hire_date ?? '',
        termination_date: employee.termination_date ?? '',
        employment_type: employee.employment_type ?? 'full_time',
        default_cost_centre_id: employee.default_cost_centre_id ?? '',
        payment_method: employee.payment_method ?? '',
        status: employee.status ?? 'active',
        bank_details_encrypted: employee.bank_details_encrypted ?? {
            bank_name: '',
            account_number: '',
            iban: '',
        },
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.hr.employees.update', employee.id));
    };

    return (
        <>
            <Head title={t('pages.hrEmployees.editTitle')} />
            <PageHeader title={t('pages.hrEmployees.editTitle')} description={employee.employee_code}>
                <Link href={route('admin.hr.employees.show', employee.id)} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="w-full max-w-3xl space-y-5">
                <EmployeeFormFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    legalEntities={legalEntities}
                    branches={branches}
                    costCentres={costCentres}
                    employmentTypes={employmentTypes}
                />
                <div className="flex flex-wrap gap-2 border-t border-rp-border pt-5">
                    <Button type="submit" disabled={processing}>
                        {t('pages.hrEmployees.updateSubmit')}
                    </Button>
                    <Link href={route('admin.hr.employees.show', employee.id)} className="rp-btn-outline">
                        {t('confirm.cancel')}
                    </Link>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Edit);
