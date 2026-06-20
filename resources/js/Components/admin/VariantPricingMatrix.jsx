import AdminFormField from '@/Components/common/AdminFormField';
import MultiSelect from '@/Components/ui/multi-select';
import Select from '@/Components/ui/select';
import { useTranslation } from 'react-i18next';

export default function VariantPricingMatrix({
    variants,
    onChange,
    canShowCost,
    supplierOptions = [],
    errors = {},
}) {
    const { t } = useTranslation();

    const alternateSupplierOptions = (preferredSupplierId) =>
        supplierOptions.filter((option) => option.value !== String(preferredSupplierId ?? ''));

    const updateRow = (index, patch) => {
        onChange(variants.map((variant, rowIndex) => (rowIndex === index ? { ...variant, ...patch } : variant)));
    };

    if (variants.length === 0) {
        return (
            <p className="text-sm text-rp-text-muted">
                {t('pages.products.variantPricingEmpty')}
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-sm">
                <thead>
                    <tr className="border-b border-rp-border text-left text-rp-text-muted">
                        <th className="pb-2 pr-4">{t('pages.products.fields.variant')}</th>
                        {canShowCost && (
                            <th className="pb-2 pr-4">{t('pages.products.fields.costPrice')}</th>
                        )}
                        <th className="pb-2 pr-4">{t('pages.products.fields.sellPrice')}</th>
                        <th className="pb-2 pr-4">{t('pages.products.fields.reorderPoint')}</th>
                        {supplierOptions.length > 0 && (
                            <>
                                <th className="pb-2 pr-4">
                                    {t('pages.products.fields.preferredSupplier')}
                                </th>
                                <th className="pb-2">
                                    {t('pages.products.fields.alternateSuppliers')}
                                </th>
                            </>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {variants.map((variant, index) => (
                        <tr key={attributeRowKey(variant, index)} className="border-b border-rp-border/50">
                            <td className="py-2 pr-4">
                                <div className="font-medium text-rp-text">{variant.name}</div>
                                <div className="text-xs text-rp-text-muted">
                                    {Object.entries(variant.attributes ?? {})
                                        .map(([key, value]) => `${key}: ${value}`)
                                        .join(' · ')}
                                </div>
                            </td>
                            {canShowCost && (
                                <td className="py-2 pr-4">
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={variant.cost_price ?? ''}
                                        className="rp-form-input w-24"
                                        onChange={(e) =>
                                            updateRow(index, { cost_price: e.target.value })
                                        }
                                    />
                                </td>
                            )}
                            <td className="py-2 pr-4">
                                <AdminFormField
                                    error={errors[`variants.${index}.sell_price`]}
                                    className="mb-0"
                                >
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={variant.sell_price ?? ''}
                                        className="rp-form-input w-24"
                                        required
                                        onChange={(e) =>
                                            updateRow(index, { sell_price: e.target.value })
                                        }
                                    />
                                </AdminFormField>
                            </td>
                            <td className="py-2 pr-4">
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="—"
                                    value={variant.reorder_point ?? ''}
                                    className="rp-form-input w-20"
                                    onChange={(e) =>
                                        updateRow(index, { reorder_point: e.target.value })
                                    }
                                />
                            </td>
                            {supplierOptions.length > 0 && (
                                <>
                                    <td className="py-2 pr-4 min-w-[180px]">
                                        <Select
                                            options={supplierOptions}
                                            value={variant.preferred_supplier_id ?? ''}
                                            placeholder={t('pages.products.none')}
                                            isClearable
                                            onChange={(value) => {
                                                const preferredSupplierId = value || null;
                                                const alternateSupplierIds = (
                                                    variant.alternate_supplier_ids ?? []
                                                ).filter(
                                                    (id) =>
                                                        String(id) !==
                                                        String(preferredSupplierId ?? ''),
                                                );
                                                updateRow(index, {
                                                    preferred_supplier_id: preferredSupplierId,
                                                    alternate_supplier_ids: alternateSupplierIds,
                                                });
                                            }}
                                        />
                                    </td>
                                    <td className="py-2 min-w-[200px]">
                                        <MultiSelect
                                            options={alternateSupplierOptions(
                                                variant.preferred_supplier_id,
                                            )}
                                            value={(variant.alternate_supplier_ids ?? []).map(String)}
                                            placeholder={t('pages.products.none')}
                                            onChange={(values) =>
                                                updateRow(index, {
                                                    alternate_supplier_ids: values.map((value) =>
                                                        Number(value),
                                                    ),
                                                })
                                            }
                                        />
                                    </td>
                                </>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function attributeRowKey(variant, index) {
    if (variant.attributes && typeof variant.attributes === 'object') {
        return JSON.stringify(variant.attributes);
    }

    return String(index);
}
