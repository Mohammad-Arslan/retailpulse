import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import {
    emptyEmployeeForm,
    prepareEmployeeFormPayload,
} from '@/Pages/Admin/Hr/Employees/employeeFormState';
import EmployeeCreateWizard from '@/Pages/Admin/Hr/Employees/EmployeeCreateWizard';
import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

function Create(props) {
    const { t } = useTranslation();
    const form = useForm(emptyEmployeeForm(props));

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => prepareEmployeeFormPayload(data));
        form.post(route('admin.hr.employees.store'), {
            forceFormData: true,
            onFinish: () => form.transform((data) => data),
        });
    };

    return (
        <>
            <Head title={t('pages.hrEmployees.createTitle')} />
            <PageHeader
                title={t('pages.hrEmployees.createTitle')}
                description={t('pages.hrEmployees.createDescription')}
            />
            <EmployeeCreateWizard
                data={form.data}
                setData={form.setData}
                errors={form.errors}
                processing={form.processing}
                onSubmit={submit}
                cancelHref={route('admin.hr.employees.index')}
                {...props}
            />
        </>
    );
}

export default withAdminLayout(Create);
