import EmployeeWizardStepper from '@/Components/admin/hr/EmployeeWizardStepper';
import { Button } from '@/Components/ui/button';
import EmployeeFormSections from '@/Pages/Admin/Hr/Employees/EmployeeFormSections';
import {
    CREATE_WIZARD_STEPS,
    firstWizardStepWithErrors,
    validateWizardStep,
} from '@/Pages/Admin/Hr/Employees/employeeFormState';
import { Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function EmployeeCreateWizard({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    cancelHref,
    ...sectionProps
}) {
    const { t } = useTranslation();
    const [stepId, setStepId] = useState(data.active_tab || 'basic');
    const [completedThroughIndex, setCompletedThroughIndex] = useState(-1);
    const [clientErrors, setClientErrors] = useState({});

    const stepIndex = Math.max(
        0,
        CREATE_WIZARD_STEPS.findIndex((step) => step.id === stepId),
    );
    const isLastStep = stepIndex === CREATE_WIZARD_STEPS.length - 1;

    const mergedErrors = useMemo(
        () => ({ ...clientErrors, ...errors }),
        [clientErrors, errors],
    );

    useEffect(() => {
        const failStep = firstWizardStepWithErrors(errors);
        if (failStep) {
            setStepId(failStep);
            setData('active_tab', failStep);
            const idx = CREATE_WIZARD_STEPS.findIndex((step) => step.id === failStep);
            if (idx >= 0) {
                setCompletedThroughIndex((prev) => Math.max(prev, idx - 1));
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [errors]);

    const goToStep = (id) => {
        setStepId(id);
        setData('active_tab', id);
        setClientErrors({});
    };

    const handleContinue = () => {
        const stepErrors = validateWizardStep(stepId, data);
        if (Object.keys(stepErrors).length > 0) {
            const labeled = {};
            for (const key of Object.keys(stepErrors)) {
                labeled[key] = t('pages.hrEmployees.wizard.fieldRequired');
            }
            setClientErrors(labeled);
            return;
        }

        setClientErrors({});
        setCompletedThroughIndex((prev) => Math.max(prev, stepIndex));

        if (!isLastStep) {
            const next = CREATE_WIZARD_STEPS[stepIndex + 1];
            goToStep(next.id);
        }
    };

    const handleBack = () => {
        if (stepIndex <= 0) {
            return;
        }
        goToStep(CREATE_WIZARD_STEPS[stepIndex - 1].id);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        for (let i = 0; i < CREATE_WIZARD_STEPS.length; i += 1) {
            const step = CREATE_WIZARD_STEPS[i];
            const stepErrors = validateWizardStep(step.id, data);
            if (Object.keys(stepErrors).length > 0) {
                const labeled = {};
                for (const key of Object.keys(stepErrors)) {
                    labeled[key] = t('pages.hrEmployees.wizard.fieldRequired');
                }
                setClientErrors(labeled);
                goToStep(step.id);
                return;
            }
        }

        setClientErrors({});
        onSubmit(e);
    };

    return (
        <form onSubmit={handleSubmit} className="w-full space-y-4" encType="multipart/form-data">
            <div className="rounded-xl border border-rp-border bg-rp-surface p-4 sm:p-5">
                <EmployeeWizardStepper
                    currentStepId={stepId}
                    completedThroughIndex={completedThroughIndex}
                    onStepClick={goToStep}
                />
                <p className="mt-3 text-sm text-rp-text-muted">{t('pages.hrEmployees.createDescription')}</p>
            </div>

            <div className="rounded-xl border border-rp-border bg-rp-surface p-4 sm:p-6">
                <h2 className="mb-4 text-lg font-semibold text-rp-text">
                    {t(`pages.hrEmployees.wizard.steps.${CREATE_WIZARD_STEPS[stepIndex].labelKey}`)}
                </h2>
                <EmployeeFormSections
                    section={stepId}
                    data={data}
                    setData={setData}
                    errors={mergedErrors}
                    readOnly={false}
                    hideSecondaryBranches
                    showBanksOptionalHint={stepId === 'banks'}
                    {...sectionProps}
                />
            </div>

            <div className="sticky bottom-0 z-10 -mx-1 flex flex-wrap items-center justify-between gap-3 border-t border-rp-border bg-rp-surface/95 px-1 py-4 backdrop-blur supports-[backdrop-filter]:bg-rp-surface/80">
                <div className="flex flex-wrap gap-2">
                    {stepIndex > 0 && (
                        <Button type="button" variant="outline" onClick={handleBack}>
                            {t('pages.hrEmployees.wizard.back')}
                        </Button>
                    )}
                    <Link href={cancelHref} className="rp-btn-outline">
                        {t('confirm.cancel')}
                    </Link>
                </div>
                <div className="flex flex-wrap gap-2">
                    {!isLastStep && (
                        <Button type="button" onClick={handleContinue}>
                            {t('pages.hrEmployees.wizard.continue')}
                        </Button>
                    )}
                    {isLastStep && (
                        <Button type="submit" disabled={processing}>
                            {t('pages.hrEmployees.createSubmit')}
                        </Button>
                    )}
                </div>
            </div>
        </form>
    );
}
