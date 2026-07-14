import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import EmployeeFormFields from './EmployeeFormFields';

function Create({ legalEntities = [], branches = [], costCentres = [], employmentTypes = [] }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        legal_entity_id: legalEntities[0]?.id ?? '',
        primary_branch_id: branches[0]?.id ?? '',
        hire_date: new Date().toISOString().slice(0, 10),
        termination_date: '',
        employment_type: 'full_time',
        default_cost_centre_id: '',
        payment_method: '',
        status: 'active',
        bank_details_encrypted: { bank_name: '', account_number: '', iban: '' },
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.hr.employees.store'));
    };

    return (
        <>
            <Head title={t('pages.hrEmployees.createTitle')} />
            <PageHeader title={t('pages.hrEmployees.createTitle')} description={t('pages.hrEmployees.createDescription')}>
                <Link href={route('admin.hr.employees.index')} className="rp-btn-outline">
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
                        {t('pages.hrEmployees.createSubmit')}
                    </Button>
                    <Link href={route('admin.hr.employees.index')} className="rp-btn-outline">
                        {t('confirm.cancel')}
                    </Link>
                </div>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
