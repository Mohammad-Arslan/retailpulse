import ComboBundleBuilder from '@/Components/admin/ComboBundleBuilder';
import VariantAttributeBuilder from '@/Components/admin/VariantAttributeBuilder';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import Select, { mapToSelectOptions } from '@/Components/ui/select';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

const TYPE_LABELS = {
    standard: 'Standard',
    variable: 'Variable',
    service: 'Service',
    digital: 'Digital',
    serialized: 'Serialized',
    combo: 'Combo / Bundle',
};

export default function ProductFormFields({
    data,
    setData,
    errors,
    productTypes,
    categories,
    brands,
    units,
    branches,
    canShowCost,
    isEdit = false,
    productId = null,
}) {
    const { t } = useTranslation();
    const typeOptions = useMemo(
        () =>
            productTypes.map((type) => ({
                value: type,
                label: t(`pages.products.types.${type}`, {
                    defaultValue: TYPE_LABELS[type] ?? type,
                }),
            })),
        [productTypes, t],
    );
    const categoryOptions = useMemo(
        () => mapToSelectOptions(categories),
        [categories],
    );
    const brandOptions = useMemo(() => mapToSelectOptions(brands), [brands]);
    const unitOptions = useMemo(
        () =>
            units.map((unit) => ({
                value: String(unit.id),
                label: `${unit.name} (${unit.abbreviation})`,
            })),
        [units],
    );
    const isVariable = data.type === 'variable';
    const isCombo = data.type === 'combo';
    const isSimple = !isVariable && !isCombo;
    const showDefaultPricing = isSimple || isCombo || (isVariable && !isEdit);
    const showVariantEditor =
        isVariable && isEdit && !data.regenerate_variants && data.variants?.length > 0;
    const showBranchPricing = branches.length > 0 && (isSimple || isCombo);

    const updateBranchPrice = (branchId, sellPrice) => {
        const existing = data.branch_prices.filter((p) => p.branch_id !== branchId);
        if (sellPrice !== '' && sellPrice !== null) {
            existing.push({ branch_id: branchId, sell_price: sellPrice });
        }
        setData('branch_prices', existing);
    };

    const branchPriceValue = (branchId) =>
        data.branch_prices.find((p) => p.branch_id === branchId)?.sell_price ?? '';

    const generalCard = (
        <FormCard className="max-w-none w-full">
            <h3 className="rp-form-label mb-4">{t('pages.products.sections.general')}</h3>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {!isEdit && (
                    <AdminFormField
                        label={t('pages.products.fields.type')}
                        id="type"
                        error={errors.type}
                        className="md:col-span-2"
                    >
                        <Select
                            id="type"
                            options={typeOptions}
                            value={data.type}
                            onChange={(value) => setData('type', value)}
                        />
                    </AdminFormField>
                )}
                <AdminFormField
                    label={t('pages.products.fields.name')}
                    id="name"
                    error={errors.name}
                    className="md:col-span-2"
                >
                    <input
                        id="name"
                        value={data.name}
                        className="rp-form-input"
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                </AdminFormField>
                <AdminFormField
                    label={t('pages.products.fields.description')}
                    id="description"
                    error={errors.description}
                    className="md:col-span-2"
                >
                    <textarea
                        id="description"
                        value={data.description}
                        rows={3}
                        className="rp-form-input"
                        onChange={(e) => setData('description', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField
                    label={t('pages.products.fields.category')}
                    id="category_id"
                    error={errors.category_id}
                >
                    <Select
                        id="category_id"
                        options={categoryOptions}
                        value={data.category_id}
                        placeholder={t('pages.products.none')}
                        isClearable
                        onChange={(value) => setData('category_id', value || null)}
                    />
                </AdminFormField>
                <AdminFormField
                    label={t('pages.products.fields.brand')}
                    id="brand_id"
                    error={errors.brand_id}
                >
                    <Select
                        id="brand_id"
                        options={brandOptions}
                        value={data.brand_id}
                        placeholder={t('pages.products.none')}
                        isClearable
                        onChange={(value) => setData('brand_id', value || null)}
                    />
                </AdminFormField>
                <AdminFormField
                    label={t('pages.products.fields.unit')}
                    id="unit_id"
                    error={errors.unit_id}
                    className="md:col-span-2"
                >
                    <Select
                        id="unit_id"
                        options={unitOptions}
                        value={data.unit_id}
                        placeholder={t('pages.products.none')}
                        isClearable
                        onChange={(value) => setData('unit_id', value || null)}
                    />
                </AdminFormField>
                <div className="flex flex-wrap gap-4 md:col-span-2">
                    <label className="flex items-center gap-2 text-sm text-rp-text-secondary">
                        <input
                            type="checkbox"
                            checked={data.track_batches}
                            onChange={(e) => setData('track_batches', e.target.checked)}
                        />
                        {t('pages.products.fields.trackBatches')}
                    </label>
                    <label className="flex items-center gap-2 text-sm text-rp-text-secondary">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        {t('pages.products.fields.active')}
                    </label>
                    {data.type === 'serialized' && (
                        <span className="text-xs text-teal-600">
                            {t('pages.products.serializedHint')}
                        </span>
                    )}
                </div>
            </div>
        </FormCard>
    );

    const attributesCard = isVariable ? (
        <FormCard className="max-w-none w-full">
            <h3 className="rp-form-label mb-4">{t('pages.products.sections.attributes')}</h3>
            <VariantAttributeBuilder
                attributes={data.variant_attributes}
                onChange={(variant_attributes) => setData('variant_attributes', variant_attributes)}
                error={errors.variant_attributes}
            />
            {isEdit && (
                <label className="mt-4 flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400">
                    <input
                        type="checkbox"
                        checked={data.regenerate_variants}
                        onChange={(e) => setData('regenerate_variants', e.target.checked)}
                    />
                    {t('pages.products.regenerateVariants')}
                </label>
            )}
        </FormCard>
    ) : null;

    const bundleCard = isCombo ? (
        <FormCard className="max-w-none w-full">
            <h3 className="rp-form-label mb-4">{t('pages.products.sections.bundle')}</h3>
            <ComboBundleBuilder
                items={data.bundle_items}
                onChange={(bundle_items) => setData('bundle_items', bundle_items)}
                excludeProductId={productId}
                error={errors.bundle_items}
            />
        </FormCard>
    ) : null;

    const pricingCard = showDefaultPricing ? (
        <FormCard className="max-w-none w-full">
            <h3 className="rp-form-label mb-4">{t('pages.products.sections.pricing')}</h3>
            <p className="mb-3 text-xs text-rp-text-muted">{t('pages.products.autoIdentifiers')}</p>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {canShowCost && (
                    <AdminFormField
                        label={t('pages.products.fields.costPrice')}
                        id="default_cost_price"
                        error={errors.default_cost_price}
                    >
                        <input
                            id="default_cost_price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.default_cost_price}
                            className="rp-form-input"
                            onChange={(e) => setData('default_cost_price', e.target.value)}
                        />
                    </AdminFormField>
                )}
                <AdminFormField
                    label={t('pages.products.fields.sellPrice')}
                    id="default_sell_price"
                    error={errors.default_sell_price}
                >
                    <input
                        id="default_sell_price"
                        type="number"
                        min="0"
                        step="0.01"
                        value={data.default_sell_price}
                        className="rp-form-input"
                        onChange={(e) => setData('default_sell_price', e.target.value)}
                    />
                </AdminFormField>
                <AdminFormField
                    label={t('pages.products.fields.reorderPoint')}
                    id="default_reorder_point"
                    error={errors.default_reorder_point}
                    className={canShowCost ? 'sm:col-span-2' : undefined}
                >
                    <input
                        id="default_reorder_point"
                        type="number"
                        min="0"
                        step="1"
                        placeholder={t('pages.products.reorderPointPlaceholder')}
                        value={data.default_reorder_point ?? ''}
                        className="rp-form-input"
                        onChange={(e) => setData('default_reorder_point', e.target.value)}
                    />
                </AdminFormField>
            </div>
        </FormCard>
    ) : null;

    const variantsCard = showVariantEditor ? (
        <FormCard className="max-w-none w-full">
            <h3 className="rp-form-label mb-4">{t('pages.products.sections.variants')}</h3>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-rp-border text-left text-rp-text-muted">
                            <th className="pb-2 pr-4">{t('pages.products.fields.variant')}</th>
                            <th className="pb-2 pr-4">SKU</th>
                            {canShowCost && (
                                <th className="pb-2 pr-4">{t('pages.products.fields.costPrice')}</th>
                            )}
                            <th className="pb-2 pr-4">{t('pages.products.fields.sellPrice')}</th>
                            <th className="pb-2">{t('pages.products.fields.reorderPoint')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.variants.map((variant, index) => (
                            <tr key={variant.id ?? index} className="border-b border-rp-border/50">
                                <td className="py-2 pr-4 font-medium">{variant.name}</td>
                                <td className="py-2 pr-4 font-mono text-xs">{variant.sku}</td>
                                {canShowCost && (
                                    <td className="py-2 pr-4">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={variant.cost_price}
                                            className="rp-form-input w-24"
                                            onChange={(e) => {
                                                const variants = [...data.variants];
                                                variants[index] = {
                                                    ...variant,
                                                    cost_price: e.target.value,
                                                };
                                                setData('variants', variants);
                                            }}
                                        />
                                    </td>
                                )}
                                <td className="py-2 pr-4">
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={variant.sell_price}
                                        className="rp-form-input w-24"
                                        onChange={(e) => {
                                            const variants = [...data.variants];
                                            variants[index] = {
                                                ...variant,
                                                sell_price: e.target.value,
                                            };
                                            setData('variants', variants);
                                        }}
                                    />
                                </td>
                                <td className="py-2">
                                    <input
                                        type="number"
                                        min="0"
                                        step="1"
                                        placeholder="—"
                                        value={variant.reorder_point ?? ''}
                                        className="rp-form-input w-20"
                                        onChange={(e) => {
                                            const variants = [...data.variants];
                                            variants[index] = {
                                                ...variant,
                                                reorder_point: e.target.value,
                                            };
                                            setData('variants', variants);
                                        }}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </FormCard>
    ) : null;

    const identifiersCard =
        isEdit && data.variants?.length > 0 ? (
            <FormCard className="max-w-none w-full">
                <h3 className="rp-form-label mb-2">{t('pages.products.sections.identifiers')}</h3>
                <ul className="space-y-1 font-mono text-xs text-rp-text-secondary">
                    {data.variants.map((v) => (
                        <li key={v.id}>
                            {v.name}: {v.sku}
                            {v.barcode ? ` · ${v.barcode}` : ''}
                        </li>
                    ))}
                </ul>
            </FormCard>
        ) : null;

    const branchPricingCard = showBranchPricing ? (
        <FormCard className="max-w-none w-full">
            <h3 className="rp-form-label mb-4">{t('pages.products.sections.branchPricing')}</h3>
            <p className="mb-3 text-xs text-rp-text-muted">
                {t('pages.products.branchPricingHint')}
            </p>
            <div className="space-y-3">
                {branches.map((branch) => (
                    <div
                        key={branch.id}
                        className="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_140px] sm:items-center"
                    >
                        <span className="text-sm text-rp-text">{branch.name}</span>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder={t('pages.products.useDefaultPrice')}
                            value={branchPriceValue(branch.id)}
                            className="rp-form-input w-full sm:w-[140px]"
                            onChange={(e) => updateBranchPrice(branch.id, e.target.value)}
                        />
                    </div>
                ))}
            </div>
        </FormCard>
    ) : null;

    const leftColumn = [generalCard, attributesCard, bundleCard].filter(Boolean);
    const rightColumn = [pricingCard, branchPricingCard, identifiersCard].filter(Boolean);

    return (
        <div className="space-y-5">
            <div className="grid grid-cols-1 items-start gap-5 lg:grid-cols-2">
                <div className="space-y-5">{leftColumn}</div>
                <div className="space-y-5">{rightColumn.length > 0 && rightColumn}</div>
            </div>
            {variantsCard}
        </div>
    );
}
