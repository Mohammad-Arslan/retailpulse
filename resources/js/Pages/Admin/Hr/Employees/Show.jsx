import EmployeeTerminationActions from '@/Components/admin/hr/EmployeeTerminationActions';
import PageHeader from '@/Components/common/PageHeader';
import { Button } from '@/Components/ui/button';
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
                <div className="flex flex-wrap items-center gap-2">
                    <Button type="button" variant="outline" asChild>
                        <Link href={route('admin.hr.employees.index')}>{t('common.back')}</Link>
                    </Button>
                    <EmployeeTerminationActions employee={employee} />
                    {can('hr.manage-employees') && (
                        <Button variant="brand" asChild>
                            <Link
                                href={route('admin.hr.employees.edit', {
                                    employee: employee.id,
                                    tab: activeTab,
                                })}
                            >
                                {t('common.edit')}
                            </Link>
                        </Button>
                    )}
                </div>
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
