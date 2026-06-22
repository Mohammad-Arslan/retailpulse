import AdminFormField from '@/Components/common/AdminFormField';
import Select, { mapRecordToSelectOptions } from '@/Components/ui/select';

export default function SettingsFields({ fields, values, setValue, errors, disabled }) {
    return (
        <div className="space-y-4">
            {fields.map((field) => {
                const id = `setting-${field.key}`;
                const error = errors?.[`values.${field.key}`] ?? errors?.[field.key];

                return (
                    <AdminFormField
                        key={field.key}
                        label={field.type === 'boolean' ? undefined : field.label}
                        id={id}
                        hint={field.description || undefined}
                        error={error}
                    >
                        {field.type === 'boolean' ? (
                            <label className="rp-checkbox-label">
                                <input
                                    id={id}
                                    type="checkbox"
                                    checked={Boolean(values[field.key])}
                                    disabled={disabled}
                                    className="accent-teal-500"
                                    onChange={(e) =>
                                        setValue(field.key, e.target.checked)
                                    }
                                />
                                <span className="text-sm text-ink-600 dark:text-ink-300">
                                    {field.label}
                                </span>
                            </label>
                        ) : field.type === 'textarea' ? (
                            <textarea
                                id={id}
                                rows={3}
                                disabled={disabled}
                                className="rp-form-input"
                                value={values[field.key] ?? ''}
                                onChange={(e) =>
                                    setValue(field.key, e.target.value)
                                }
                            />
                        ) : field.type === 'select' ? (
                            <Select
                                id={id}
                                isDisabled={disabled}
                                options={mapRecordToSelectOptions(field.options)}
                                value={values[field.key] ?? ''}
                                onChange={(value) => setValue(field.key, value ?? '')}
                            />
                        ) : field.type === 'multiselect' ? (
                            <div className="space-y-2">
                                {Object.entries(field.options ?? {}).map(([value, label]) => {
                                    const selected = Array.isArray(values[field.key])
                                        ? values[field.key]
                                        : [];

                                    return (
                                        <label key={value} className="rp-checkbox-label">
                                            <input
                                                type="checkbox"
                                                disabled={disabled}
                                                checked={selected.includes(value)}
                                                className="accent-teal-500"
                                                onChange={(e) => {
                                                    const next = new Set(selected);
                                                    if (e.target.checked) {
                                                        next.add(value);
                                                    } else {
                                                        next.delete(value);
                                                    }
                                                    setValue(field.key, Array.from(next));
                                                }}
                                            />
                                            <span className="text-sm text-ink-600 dark:text-ink-300">
                                                {label}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        ) : field.type === 'encrypted' ? (
                            <input
                                id={id}
                                type="password"
                                autoComplete="new-password"
                                disabled={disabled}
                                placeholder={
                                    values[field.key] === '********'
                                        ? 'Leave blank to keep current value'
                                        : ''
                                }
                                className="rp-form-input"
                                value={
                                    values[field.key] === '********'
                                        ? ''
                                        : (values[field.key] ?? '')
                                }
                                onChange={(e) =>
                                    setValue(
                                        field.key,
                                        e.target.value === ''
                                            ? '********'
                                            : e.target.value,
                                    )
                                }
                            />
                        ) : field.type === 'integer' ? (
                            <input
                                id={id}
                                type="number"
                                disabled={disabled}
                                className="rp-form-input"
                                value={values[field.key] ?? ''}
                                onChange={(e) =>
                                    setValue(field.key, e.target.value)
                                }
                            />
                        ) : (
                            <input
                                id={id}
                                type={field.type === 'email' ? 'email' : 'text'}
                                disabled={disabled}
                                className="rp-form-input"
                                value={values[field.key] ?? ''}
                                onChange={(e) =>
                                    setValue(field.key, e.target.value)
                                }
                            />
                        )}
                    </AdminFormField>
                );
            })}
        </div>
    );
}
