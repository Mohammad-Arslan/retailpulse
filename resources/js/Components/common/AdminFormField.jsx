import InputError from '@/Components/InputError';

export default function AdminFormField({
    label,
    id,
    error,
    hint,
    required = false,
    children,
    className = '',
}) {
    return (
        <div className={className}>
            {label && (
                <label htmlFor={id} className="rp-form-label">
                    {label}
                    {required && <span className="ml-0.5 text-rose-500">*</span>}
                </label>
            )}
            {children}
            {hint && !error && (
                <p className="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{hint}</p>
            )}
            {error && <InputError message={error} className="mt-2" />}
        </div>
    );
}
