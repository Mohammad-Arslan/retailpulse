import InputError from '@/Components/InputError';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';

export default function FormField({
    label,
    id,
    error,
    hint,
    required = false,
    children,
    className = '',
}) {
    return (
        <div className={cn('space-y-2', className)}>
            {label && (
                <Label htmlFor={id} className="rp-form-label">
                    {label}
                    {required && (
                        <span className="text-rose-500" aria-hidden>
                            {' '}
                            *
                        </span>
                    )}
                </Label>
            )}
            {children}
            {hint && !error && (
                <p className="text-xs text-rp-text-muted">{hint}</p>
            )}
            {error && <InputError message={error} />}
        </div>
    );
}
