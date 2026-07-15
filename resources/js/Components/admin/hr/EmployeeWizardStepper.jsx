import { CREATE_WIZARD_STEPS } from '@/Pages/Admin/Hr/Employees/employeeFormState';
import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function EmployeeWizardStepper({ currentStepId, onStepClick, completedThroughIndex = -1 }) {
    const { t } = useTranslation();
    const currentIndex = Math.max(
        0,
        CREATE_WIZARD_STEPS.findIndex((step) => step.id === currentStepId),
    );

    return (
        <nav aria-label={t('pages.hrEmployees.wizard.progressLabel')} className="w-full">
            <p className="mb-3 text-sm text-rp-text-muted sm:hidden">
                {t('pages.hrEmployees.wizard.stepOf', {
                    current: currentIndex + 1,
                    total: CREATE_WIZARD_STEPS.length,
                })}
            </p>
            <ol className="flex flex-wrap items-center gap-2">
                {CREATE_WIZARD_STEPS.map((step, index) => {
                    const isComplete = index < currentIndex || index <= completedThroughIndex;
                    const isCurrent = step.id === currentStepId;
                    const canJump = index <= currentIndex || index <= completedThroughIndex + 1;

                    return (
                        <li key={step.id} className="flex min-w-0 items-center gap-2">
                            {index > 0 && (
                                <span
                                    className={cn(
                                        'hidden h-px w-8 shrink-0 sm:block',
                                        isComplete || isCurrent ? 'bg-teal-500' : 'bg-rp-border',
                                    )}
                                    aria-hidden
                                />
                            )}
                            <button
                                type="button"
                                disabled={!canJump}
                                onClick={() => canJump && onStepClick?.(step.id)}
                                className={cn(
                                    'flex min-w-0 items-center gap-2 rounded-full px-2 py-1 transition',
                                    isCurrent && 'bg-teal-500/10',
                                    canJump ? 'cursor-pointer' : 'cursor-not-allowed opacity-60',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                        (isComplete || isCurrent) && 'bg-teal-600 text-white',
                                        !isComplete && !isCurrent && 'bg-rp-surface-inset text-rp-text-muted',
                                    )}
                                >
                                    {isComplete && !isCurrent ? <Check className="h-3.5 w-3.5" /> : index + 1}
                                </span>
                                <span
                                    className={cn(
                                        'hidden truncate text-sm font-medium md:inline',
                                        isCurrent ? 'text-rp-text' : 'text-rp-text-muted',
                                    )}
                                >
                                    {t(`pages.hrEmployees.wizard.steps.${step.labelKey}`)}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
