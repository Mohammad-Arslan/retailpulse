import AdminFormField from '@/Components/common/AdminFormField';
import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function VariantAttributeBuilder({ attributes, onChange, error }) {
    const { t } = useTranslation();

    const addAttribute = () => {
        onChange([...attributes, { name: '', options: [''] }]);
    };

    const removeAttribute = (index) => {
        onChange(attributes.filter((_, i) => i !== index));
    };

    const updateAttribute = (index, field, value) => {
        onChange(
            attributes.map((attr, i) =>
                i === index ? { ...attr, [field]: value } : attr,
            ),
        );
    };

    const updateOption = (attrIndex, optIndex, value) => {
        const next = [...attributes];
        const options = [...next[attrIndex].options];
        options[optIndex] = value;
        next[attrIndex] = { ...next[attrIndex], options };
        onChange(next);
    };

    const addOption = (attrIndex) => {
        const next = [...attributes];
        next[attrIndex] = {
            ...next[attrIndex],
            options: [...next[attrIndex].options, ''],
        };
        onChange(next);
    };

    const removeOption = (attrIndex, optIndex) => {
        const next = [...attributes];
        next[attrIndex] = {
            ...next[attrIndex],
            options: next[attrIndex].options.filter((_, i) => i !== optIndex),
        };
        onChange(next);
    };

    return (
        <div className="space-y-4">
            {error && (
                <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
            )}
            {attributes.map((attr, attrIndex) => (
                <div
                    key={attrIndex}
                    className="rounded-xl border border-rp-border bg-rp-surface p-4 space-y-3"
                >
                    <div className="flex items-start gap-3">
                        <AdminFormField
                            label={t('pages.products.fields.attributeName')}
                            id={`attr-name-${attrIndex}`}
                            className="flex-1"
                        >
                            <input
                                id={`attr-name-${attrIndex}`}
                                value={attr.name}
                                className="rp-form-input"
                                placeholder={t('pages.products.placeholders.attributeName')}
                                onChange={(e) =>
                                    updateAttribute(attrIndex, 'name', e.target.value)
                                }
                            />
                        </AdminFormField>
                        <button
                            type="button"
                            onClick={() => removeAttribute(attrIndex)}
                            className="mt-7 rounded-lg p-2 text-rp-text-muted hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                        >
                            <Trash2 className="h-4 w-4" />
                        </button>
                    </div>
                    <div className="space-y-2">
                        <p className="rp-form-label">
                            {t('pages.products.fields.attributeOptions')}
                        </p>
                        {attr.options.map((option, optIndex) => (
                            <div key={optIndex} className="flex items-center gap-2">
                                <input
                                    value={option}
                                    className="rp-form-input flex-1"
                                    placeholder={t('pages.products.placeholders.option')}
                                    onChange={(e) =>
                                        updateOption(attrIndex, optIndex, e.target.value)
                                    }
                                />
                                {attr.options.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => removeOption(attrIndex, optIndex)}
                                        className="rounded-lg p-2 text-rp-text-muted hover:text-red-600"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                )}
                            </div>
                        ))}
                        <button
                            type="button"
                            onClick={() => addOption(attrIndex)}
                            className="text-xs font-medium text-teal-600 hover:text-teal-700"
                        >
                            + {t('pages.products.addOption')}
                        </button>
                    </div>
                </div>
            ))}
            <button type="button" onClick={addAttribute} className="rp-btn-outline">
                <Plus className="h-4 w-4" />
                {t('pages.products.addAttribute')}
            </button>
        </div>
    );
}
