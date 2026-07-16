import EmployeeSectionNav from '@/Components/admin/hr/EmployeeSectionNav';
import { Button } from '@/Components/ui/button';
import EmployeeFormSections from '@/Pages/Admin/Hr/Employees/EmployeeFormSections';
import {
    EMPLOYEE_TABS,
    firstTabWithErrors,
    tabsWithErrors as resolveTabsWithErrors,
} from '@/Pages/Admin/Hr/Employees/employeeFormState';
import { Link } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

/**
 * Edit/Show shell: full-width vertical section nav + sticky save bar.
 * Create uses EmployeeCreateWizard instead.
 */
export default function EmployeeTabbedForm({
    mode = 'edit',
    data,
    setData,
    errors,
    processing,
    onSubmit,
    employee = null,
    cancelHref,
    ...sectionProps
}) {
    const { t } = useTranslation();
    const readOnly = mode === 'show';
    const activeSection = data.active_tab || 'basic';
    const errorSectionIds = useMemo(() => resolveTabsWithErrors(errors), [errors]);
    const activeLabelKey =
        EMPLOYEE_TABS.find((tab) => tab.id === activeSection)?.labelKey ?? 'basic';

    useEffect(() => {
        if (readOnly) {
            return;
        }
        const section = firstTabWithErrors(errors);
        if (section && section !== activeSection) {
            setData('active_tab', section);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [errors]);

    return (
        <form onSubmit={onSubmit} className="w-full space-y-4" encType="multipart/form-data">
            <div className="grid w-full gap-4 lg:grid-cols-[260px_minmax(0,1fr)] lg:items-start">
                <aside className="rounded-xl border border-rp-border bg-rp-surface p-3 lg:sticky lg:top-4">
                    <EmployeeSectionNav
                        activeSection={activeSection}
                        onChange={(id) => setData('active_tab', id)}
                        errorSectionIds={errorSectionIds}
                        title={t('pages.hrEmployees.edit.sidebarTitle')}
                    />
                </aside>

                <div className="min-w-0 space-y-4">
                    <div className="rounded-xl border border-rp-border bg-rp-surface p-4 sm:p-6">
                        <h2 className="mb-4 text-lg font-semibold text-rp-text">
                            {t(`pages.hrEmployees.tabs.${activeLabelKey}`)}
                        </h2>
                        <EmployeeFormSections
                            section={activeSection}
                            data={data}
                            setData={setData}
                            errors={errors}
                            readOnly={readOnly}
                            employee={employee}
                            {...sectionProps}
                        />
                    </div>

                    {!readOnly && (
                        <div className="sticky bottom-0 z-10 flex flex-wrap gap-2 border-t border-rp-border bg-rp-surface/95 py-4 backdrop-blur supports-[backdrop-filter]:bg-rp-surface/80">
                            <Button type="submit" variant="brand" disabled={processing}>
                                {t('pages.hrEmployees.updateSubmit')}
                            </Button>
                            <Link href={cancelHref} className="rp-btn-outline">
                                {t('confirm.cancel')}
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </form>
    );
}
