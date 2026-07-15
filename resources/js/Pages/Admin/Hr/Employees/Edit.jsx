import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { employeeToForm, prepareEmployeeFormPayload } from '@/Pages/Admin/Hr/Employees/employeeFormState';
import EmployeeTabbedForm from '@/Pages/Admin/Hr/Employees/EmployeeTabbedForm';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';

function Edit(props) {
    const { employee, tab = 'basic' } = props;
    const { t } = useTranslation();
    const form = useForm({
        ...employeeToForm(employee),
        active_tab: tab || 'basic',
    });

    useEffect(() => {
        if (tab && tab !== form.data.active_tab) {
            form.setData('active_tab', tab);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tab]);

    const submit = (e) => {
        e.preventDefault();
        // PHP does not parse multipart bodies on PUT — spoof via POST so FormData fields arrive.
        form.transform((data) => ({
            ...prepareEmployeeFormPayload(data),
            _method: 'put',
        }));
        form.post(route('admin.hr.employees.update', employee.id), {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
    };

    return (
        <>
            <Head title={t('pages.hrEmployees.editTitle')} />
            <PageHeader title={t('pages.hrEmployees.editTitle')} description={employee.employee_code}>
                <Link href={route('admin.hr.employees.show', employee.id)} className="rp-btn-outline">
                    {t('common.view')}
                </Link>
            </PageHeader>
            <EmployeeTabbedForm
                mode="edit"
                employee={employee}
                data={form.data}
                setData={form.setData}
                errors={form.errors}
                processing={form.processing}
                onSubmit={submit}
                cancelHref={route('admin.hr.employees.show', employee.id)}
                {...props}
            />
        </>
    );
}

export default withAdminLayout(Edit);
