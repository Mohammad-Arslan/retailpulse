import { EMPLOYEE_TABS } from '@/Pages/Admin/Hr/Employees/employeeFormState';
import { cn } from '@/lib/utils';
import Select from '@/Components/ui/select';
import { useTranslation } from 'react-i18next';

export default function EmployeeSectionNav({
    activeSection,
    onChange,
    errorSectionIds = [],
    title,
}) {
    const { t } = useTranslation();

    const mobileOptions = EMPLOYEE_TABS.map((tab) => ({
        value: tab.id,
        label: t(`pages.hrEmployees.tabs.${tab.labelKey}`),
    }));

    return (
        <>
            <div className="mb-4 lg:hidden">
                <Select
                    value={activeSection}
                    options={mobileOptions}
                    onChange={(value) => onChange(value ?? 'basic')}
                />
                {errorSectionIds.length > 0 && (
                    <p className="mt-2 text-xs text-red-600">
                        {t('pages.hrEmployees.edit.sectionsWithErrors', {
                            count: errorSectionIds.length,
                        })}
                    </p>
                )}
            </div>

            <nav
                aria-label={title || t('pages.hrEmployees.edit.sidebarTitle')}
                className="hidden lg:block"
            >
                {title && (
                    <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-rp-text-muted">
                        {title}
                    </p>
                )}
                <ul className="space-y-1">
                    {EMPLOYEE_TABS.map((tab) => {
                        const Icon = tab.icon;
                        const isActive = activeSection === tab.id;
                        const hasError = errorSectionIds.includes(tab.id);

                        return (
                            <li key={tab.id}>
                                <button
                                    type="button"
                                    onClick={() => onChange(tab.id)}
                                    className={cn(
                                        'flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium transition',
                                        isActive
                                            ? 'bg-teal-500/10 text-teal-700 dark:text-teal-300'
                                            : 'text-rp-text-muted hover:bg-rp-surface-inset hover:text-rp-text',
                                        hasError && !isActive && 'text-red-600',
                                    )}
                                >
                                    <Icon className="h-4 w-4 shrink-0" />
                                    <span className="min-w-0 flex-1 truncate">
                                        {t(`pages.hrEmployees.tabs.${tab.labelKey}`)}
                                    </span>
                                    {hasError && (
                                        <span
                                            className="h-2 w-2 shrink-0 rounded-full bg-red-500"
                                            aria-hidden
                                        />
                                    )}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            </nav>
        </>
    );
}
