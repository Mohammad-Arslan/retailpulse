import { cn } from '@/lib/utils';
import { useCallback, useId, useMemo, useState } from 'react';
import ReactSelect from 'react-select';

function normalizeValues(values) {
    if (!Array.isArray(values)) {
        return [];
    }

    return values
        .filter((value) => value !== null && value !== undefined && value !== '')
        .map((value) => String(value));
}

function findOptions(options, values) {
    const normalized = normalizeValues(values);

    return normalized
        .map((value) => options.find((option) => String(option.value) === value))
        .filter(Boolean);
}

const selectStyles = {
    control: (base, state) => ({
        ...base,
        minHeight: '46px',
        borderRadius: '10px',
        borderWidth: '1.5px',
        borderColor: state.isFocused ? '#359080' : 'var(--rp-border)',
        backgroundColor: state.isFocused ? 'var(--rp-surface)' : 'var(--rp-surface-inset)',
        boxShadow: state.isFocused ? '0 0 0 3px rgba(42, 124, 111, 0.1)' : 'none',
        cursor: state.isDisabled ? 'not-allowed' : 'pointer',
        opacity: state.isDisabled ? 0.6 : 1,
        '&:hover': {
            borderColor: state.isFocused ? '#359080' : 'var(--rp-border)',
        },
    }),
    valueContainer: (base) => ({
        ...base,
        padding: '2px 12px',
    }),
    multiValue: (base) => ({
        ...base,
        borderRadius: '8px',
        backgroundColor: 'rgba(42, 124, 111, 0.12)',
    }),
    multiValueLabel: (base) => ({
        ...base,
        color: 'var(--rp-text)',
        fontSize: '0.8125rem',
    }),
    multiValueRemove: (base) => ({
        ...base,
        color: 'var(--rp-text-muted)',
        '&:hover': {
            backgroundColor: 'rgba(42, 124, 111, 0.18)',
            color: 'var(--rp-text)',
        },
    }),
    placeholder: (base) => ({
        ...base,
        color: 'var(--rp-text-muted)',
        fontSize: '0.875rem',
    }),
    input: (base) => ({
        ...base,
        color: 'var(--rp-text)',
        fontSize: '0.875rem',
        margin: 0,
        padding: 0,
    }),
    indicatorSeparator: () => ({
        display: 'none',
    }),
    dropdownIndicator: (base) => ({
        ...base,
        color: 'var(--rp-text-muted)',
        paddingRight: '12px',
        '&:hover': {
            color: 'var(--rp-text-secondary)',
        },
    }),
    clearIndicator: (base) => ({
        ...base,
        color: 'var(--rp-text-muted)',
        paddingRight: '4px',
        '&:hover': {
            color: 'var(--rp-text-secondary)',
        },
    }),
    menuPortal: (base) => ({
        ...base,
        zIndex: 200,
    }),
    menu: (base) => ({
        ...base,
        borderRadius: '10px',
        border: '1.5px solid var(--rp-border)',
        backgroundColor: 'var(--rp-surface)',
        boxShadow: '0 12px 32px rgba(26, 23, 20, 0.08)',
        overflow: 'hidden',
    }),
    menuList: (base) => ({
        ...base,
        padding: '4px',
    }),
    option: (base, state) => ({
        ...base,
        borderRadius: '8px',
        fontSize: '0.875rem',
        color: 'var(--rp-text)',
        backgroundColor: state.isSelected
            ? 'rgba(42, 124, 111, 0.12)'
            : state.isFocused
              ? 'var(--rp-surface-subtle)'
              : 'transparent',
        cursor: 'pointer',
        '&:active': {
            backgroundColor: 'rgba(42, 124, 111, 0.18)',
        },
    }),
};

export default function MultiSelect({
    options = [],
    value: valueProp,
    defaultValue,
    onChange,
    placeholder,
    isClearable = true,
    isSearchable = true,
    isDisabled = false,
    id,
    inputId,
    className,
}) {
    const generatedId = useId();
    const resolvedInputId = inputId ?? id ?? generatedId;
    const isControlled = valueProp !== undefined;
    const [uncontrolledValue, setUncontrolledValue] = useState(defaultValue ?? []);
    const currentValue = isControlled ? valueProp : uncontrolledValue;
    const selectedOptions = useMemo(
        () => findOptions(options, currentValue),
        [options, currentValue],
    );

    const handleChange = useCallback(
        (nextOptions) => {
            const next = (nextOptions ?? []).map((option) => option.value);

            if (!isControlled) {
                setUncontrolledValue(next);
            }

            onChange?.(next);
        },
        [isControlled, onChange],
    );

    return (
        <div className={cn('w-full', className)}>
            <ReactSelect
                inputId={resolvedInputId}
                instanceId={resolvedInputId}
                options={options}
                value={selectedOptions}
                onChange={handleChange}
                placeholder={placeholder}
                isMulti
                isClearable={isClearable}
                isDisabled={isDisabled}
                isSearchable={isSearchable}
                styles={selectStyles}
                classNamePrefix="rp-select"
                menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                menuPosition="fixed"
                menuPlacement="auto"
                noOptionsMessage={() => 'No options'}
            />
        </div>
    );
}
