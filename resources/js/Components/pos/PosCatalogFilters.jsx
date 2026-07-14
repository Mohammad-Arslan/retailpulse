import ScrollArea from '@/Components/common/ScrollArea';
import { cn } from '@/lib/utils';
import { useTranslation } from 'react-i18next';

function FilterChip({ label, active, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'shrink-0 rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-colors',
                active
                    ? 'border-[var(--pos-teal-600)] bg-[var(--pos-teal-600)] text-white'
                    : 'border-[var(--pos-border)] bg-[var(--pos-bg)] text-[var(--pos-text-2)] hover:border-[var(--pos-border-strong)]',
            )}
        >
            {label}
        </button>
    );
}

function ChipSection({ label, children }) {
    return (
        <div>
            <div className="mb-2 text-[10px] font-bold tracking-[0.08em] text-[var(--pos-text-3)]">
                {label}
            </div>
            <ScrollArea className="overflow-x-auto overflow-y-hidden">
                <div className="flex w-max gap-1.5 pr-1">{children}</div>
            </ScrollArea>
        </div>
    );
}

/**
 * Touch-friendly category + brand chips for POS catalog browsing.
 */
export function PosCatalogFilters({
    categories = [],
    brands = [],
    categoryId,
    brandId,
    onCategoryChange,
    onBrandChange,
}) {
    const { t } = useTranslation();

    return (
        <div className="shrink-0 space-y-3 px-4 pb-3 sm:px-5">
            <ChipSection label={t('pages.pos.catalog.category').toUpperCase()}>
                <FilterChip
                    label={t('pages.pos.catalog.allCategories')}
                    active={categoryId == null}
                    onClick={() => onCategoryChange(null)}
                />
                {categories.map((cat) => (
                    <FilterChip
                        key={cat.id}
                        label={cat.name}
                        active={categoryId === cat.id}
                        onClick={() => onCategoryChange(cat.id)}
                    />
                ))}
            </ChipSection>

            {brands.length > 0 ? (
                <ChipSection label={t('pages.pos.catalog.brand').toUpperCase()}>
                    <FilterChip
                        label={t('pages.pos.catalog.allBrands')}
                        active={brandId == null}
                        onClick={() => onBrandChange(null)}
                    />
                    {brands.map((brand) => (
                        <FilterChip
                            key={brand.id}
                            label={brand.name}
                            active={brandId === brand.id}
                            onClick={() => onBrandChange(brand.id)}
                        />
                    ))}
                </ChipSection>
            ) : null}
        </div>
    );
}
