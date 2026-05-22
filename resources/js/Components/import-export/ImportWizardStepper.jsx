import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';

export default function ImportWizardStepper({ steps, currentStep }) {
    return (
        <nav aria-label="Import progress" className="border-b border-rp-border px-6 py-4">
            <ol className="flex flex-wrap items-center gap-2">
                {steps.map((step, index) => {
                    const stepNumber = index + 1;
                    const isComplete = stepNumber < currentStep;
                    const isCurrent = stepNumber === currentStep;

                    return (
                        <li key={step.key} className="flex min-w-0 items-center gap-2">
                            {index > 0 && (
                                <span
                                    className={cn(
                                        'hidden h-px w-6 shrink-0 sm:block',
                                        isComplete ? 'bg-teal-500' : 'bg-rp-border',
                                    )}
                                    aria-hidden
                                />
                            )}
                            <div
                                className={cn(
                                    'flex min-w-0 items-center gap-2 rounded-full px-2 py-1',
                                    isCurrent && 'bg-teal-500/10',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                        isComplete && 'bg-teal-500 text-white',
                                        isCurrent && !isComplete && 'bg-teal-500 text-white',
                                        !isComplete && !isCurrent && 'bg-rp-surface-inset text-rp-text-muted',
                                    )}
                                >
                                    {isComplete ? <Check className="h-3.5 w-3.5" /> : stepNumber}
                                </span>
                                <span
                                    className={cn(
                                        'hidden truncate text-xs font-medium sm:inline',
                                        isCurrent ? 'text-rp-text' : 'text-rp-text-muted',
                                    )}
                                >
                                    {step.label}
                                </span>
                            </div>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
