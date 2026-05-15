import InputError from '@/Components/InputError';

export default function AdminFormField({
    label,
    id,
    error,
    children,
    className = '',
}) {
    return (
        <div className={className}>
            {label && (
                <label htmlFor={id} className="rp-form-label">
                    {label}
                </label>
            )}
            {children}
            {error && <InputError message={error} className="mt-2" />}
        </div>
    );
}
