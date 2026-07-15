import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { employeeToForm } from '@/Pages/Admin/Hr/Employees/employeeFormState';
import EmployeeTabbedForm from '@/Pages/Admin/Hr/Employees/EmployeeTabbedForm';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function Show(props) {
    const { employee, tab = 'basic' } = props;
    const { t } = useTranslation();
    const can = useCan();
    const [activeTab, setActiveTab] = useState(tab || 'basic');

    const data = useMemo(
        () => ({
            ...employeeToForm(employee),
            active_tab: activeTab,
        }),
        [employee, activeTab],
    );

    const setData = (key, value) => {
        if (key === 'active_tab') {
            setActiveTab(value);
        }
    };

    return (
        <>
            <Head title={employee.name} />
            <PageHeader title={employee.name} description={employee.employee_code}>
                <Link href={route('admin.hr.employees.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
                {can('hr.manage-employees') && (
                    <Link
                        href={route('admin.hr.employees.edit', { employee: employee.id, tab: activeTab })}
                        className="rp-btn-primary"
                    >
                        {t('common.edit')}
                    </Link>
                )}
            </PageHeader>

            <EmployeeTabbedForm
                mode="show"
                employee={employee}
                data={data}
                setData={setData}
                errors={{}}
                processing={false}
                onSubmit={(e) => e.preventDefault()}
                cancelHref={route('admin.hr.employees.index')}
                {...props}
            />
        </>
    );
}

export default withAdminLayout(Show);
