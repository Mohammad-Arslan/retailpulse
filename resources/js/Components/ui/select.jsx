import { cn } from '@/lib/utils';
import { useCallback, useId, useMemo, useState } from 'react';
import ReactSelect from 'react-select';

function normalizeValue(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return String(value);
}

function findOption(options, value) {
    const normalized = normalizeValue(value);

    if (normalized === null) {
        return null;
    }

    return options.find((option) => String(option.value) === normalized) ?? null;
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
    singleValue: (base) => ({
        ...base,
        color: 'var(--rp-text)',
        fontSize: '0.875rem',
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

export function mapToSelectOptions(items, { valueKey = 'id', labelKey = 'name', getLabel } = {}) {
    return items.map((item) => ({
        value: String(item[valueKey]),
        label: getLabel ? getLabel(item) : String(item[labelKey]),
    }));
}

export function mapRecordToSelectOptions(record) {
    return Object.entries(record ?? {}).map(([value, label]) => ({
        value,
        label,
    }));
}

export default function Select({
    options = [],
    value: valueProp,
    defaultValue,
    onChange,
    placeholder,
    isClearable = false,
    isSearchable = true,
    isDisabled = false,
    id,
    inputId,
    name,
    className,
    required = false,
    menuPlacement = 'auto',
}) {
    const generatedId = useId();
    const resolvedInputId = inputId ?? id ?? generatedId;
    const isControlled = valueProp !== undefined;
    const [uncontrolledValue, setUncontrolledValue] = useState(defaultValue ?? null);
    const currentValue = isControlled ? valueProp : uncontrolledValue;
    const selectedOption = useMemo(
        () => findOption(options, currentValue),
        [options, currentValue],
    );

    const handleChange = useCallback(
        (option) => {
            const next = option ? option.value : null;

            if (!isControlled) {
                setUncontrolledValue(next);
            }

            onChange?.(next);
        },
        [isControlled, onChange],
    );

    return (
        <div className={cn('w-full', className)}>
            {name ? (
                <input
                    type="hidden"
                    name={name}
                    value={currentValue ?? ''}
                    required={required && (currentValue === null || currentValue === '')}
                />
            ) : null}
            <ReactSelect
                inputId={resolvedInputId}
                instanceId={resolvedInputId}
                options={options}
                value={selectedOption}
                onChange={handleChange}
                placeholder={placeholder}
                isClearable={isClearable}
                isDisabled={isDisabled}
                isSearchable={isSearchable}
                styles={selectStyles}
                classNamePrefix="rp-select"
                menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                menuPosition="fixed"
                menuPlacement={menuPlacement}
                noOptionsMessage={() => 'No options'}
            />
        </div>
    );
}
