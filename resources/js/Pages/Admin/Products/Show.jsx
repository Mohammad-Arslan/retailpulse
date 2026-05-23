import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, Link } from '@inertiajs/react';
import { Package, Pencil } from 'lucide-react';
import { useTranslation } from 'react-i18next';

function DetailField({ label, value, className = '' }) {
    return (
        <div className={className}>
            <dt className="text-xs font-semibold uppercase tracking-wide text-rp-text-muted">
                {label}
            </dt>
            <dd className="mt-1.5 text-sm text-rp-text">{value ?? '—'}</dd>
        </div>
    );
}

function Show({ product, canShowCost }) {
    const can = useCan();
    const { t } = useTranslation();

    const isVariable = product.type === 'variable';
    const isCombo = product.type === 'combo';

    return (
        <>
            <Head title={product.name} />
            <PageHeader title={product.name} description={product.slug}>
                <div className="flex flex-wrap gap-2">
                    <Link href={route('admin.products.index')} className="rp-btn-outline">
                        {t('pages.products.backToList')}
                    </Link>
                    {can('products.update') && (
                        <Link
                            href={route('admin.products.edit', product.id)}
                            className="rp-btn-primary"
                        >
                            <Pencil className="h-4 w-4" />
                            {t('common.edit')}
                        </Link>
                    )}
                </div>
            </PageHeader>

            <div className="mb-6 flex flex-wrap items-center gap-3">
                {product.images?.length > 0 ? (
                    <div className="flex h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-rp-border">
                        <img
                            src={
                                (product.images.find((image) => image.is_primary) ?? product.images[0])
                                    ?.thumbnail_url ??
                                (product.images.find((image) => image.is_primary) ?? product.images[0])?.url
                            }
                            alt={product.name}
                            className="h-full w-full object-cover"
                        />
                    </div>
                ) : (
                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                        <Package className="h-5 w-5" />
                    </span>
                )}
                <span className="rounded-md bg-ink-100 px-2.5 py-1 text-xs font-medium capitalize dark:bg-ink-800">
                    {t(`pages.products.types.${product.type}`, { defaultValue: product.type })}
                </span>
                <span
                    className={`rounded-full px-3 py-1 text-xs font-semibold ${
                        product.is_active
                            ? 'bg-teal-100 text-teal-800 dark:bg-teal-500/20 dark:text-teal-300'
                            : 'bg-ink-100 text-rp-text-muted dark:bg-ink-800'
                    }`}
                >
                    {product.is_active
                        ? t('pages.products.active')
                        : t('pages.products.inactive')}
                </span>
            </div>

            <div className="space-y-6">
                {product.images?.length > 0 && (
                    <section className="rp-card space-y-4">
                        <h2 className="text-sm font-semibold text-rp-text">
                            {t('pages.products.sections.images')}
                        </h2>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                            {product.images.map((image) => (
                                <a
                                    key={image.id}
                                    href={image.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="group relative overflow-hidden rounded-xl border border-rp-border"
                                >
                                    <img
                                        src={image.thumbnail_url ?? image.url}
                                        alt={image.alt ?? product.name}
                                        className="aspect-square w-full object-cover transition group-hover:scale-105"
                                    />
                                    {image.is_primary && (
                                        <span className="absolute left-2 top-2 rounded-full bg-teal-600/90 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                                            {t('pages.products.primaryImage')}
                                        </span>
                                    )}
                                </a>
                            ))}
                        </div>
                    </section>
                )}

                <section className="rp-card space-y-5">
                    <h2 className="text-sm font-semibold text-rp-text">
                        {t('pages.products.sections.general')}
                    </h2>
                    <dl className="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <DetailField
                            label={t('pages.products.fields.type')}
                            value={t(`pages.products.types.${product.type}`, {
                                defaultValue: product.type,
                            })}
                        />
                        <DetailField
                            label={t('pages.products.fields.category')}
                            value={product.category?.name}
                        />
                        <DetailField
                            label={t('pages.products.fields.brand')}
                            value={product.brand?.name}
                        />
                        <DetailField
                            label={t('pages.products.fields.unit')}
                            value={
                                product.unit
                                    ? `${product.unit.name} (${product.unit.abbreviation})`
                                    : null
                            }
                        />
                        <DetailField
                            label={t('pages.products.fields.trackBatches')}
                            value={
                                product.track_batches ? t('common.yes') : t('common.no')
                            }
                        />
                        {product.track_serials && (
                            <DetailField
                                label={t('pages.products.fields.serialNumbers')}
                                value={t('pages.products.serializedHint')}
                            />
                        )}
                    </dl>
                    {product.description && (
                        <DetailField
                            label={t('pages.products.fields.description')}
                            value={
                                <span className="whitespace-pre-wrap">{product.description}</span>
                            }
                        />
                    )}
                </section>

                {isVariable && product.variant_attributes?.length > 0 && (
                    <section className="rp-card space-y-4">
                        <h2 className="text-sm font-semibold text-rp-text">
                            {t('pages.products.sections.attributes')}
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {product.variant_attributes.map((attr) => (
                                <div
                                    key={attr.name}
                                    className="rounded-lg border border-rp-border bg-rp-surface-inset px-4 py-3"
                                >
                                    <div className="text-xs font-semibold uppercase tracking-wide text-rp-text-muted">
                                        {attr.name}
                                    </div>
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        {(attr.options ?? []).map((option) => (
                                            <span
                                                key={option}
                                                className="rounded-md bg-ink-100 px-2 py-0.5 text-xs dark:bg-ink-800"
                                            >
                                                {option}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {isCombo && product.bundle_items?.length > 0 && (
                    <section className="rp-card overflow-hidden">
                        <h2 className="mb-4 text-sm font-semibold text-rp-text">
                            {t('pages.products.sections.bundle')}
                        </h2>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-rp-border bg-sand-50/80 text-left text-xs font-semibold uppercase tracking-wide text-rp-text-muted dark:bg-ink-900/50">
                                    <th className="px-4 py-3">{t('pages.products.fields.name')}</th>
                                    <th className="px-4 py-3">SKU</th>
                                    <th className="px-4 py-3 text-right">
                                        {t('pages.inventory.fields.quantity')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {product.bundle_items.map((item, index) => (
                                    <tr
                                        key={`${item.child?.id ?? index}-${index}`}
                                        className="border-b border-rp-border last:border-0"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium text-rp-text">
                                                {item.child?.product_name ?? '—'}
                                            </div>
                                            <div className="text-xs text-rp-text-muted">
                                                {item.child?.name}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-rp-text-secondary">
                                            {item.child?.sku ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            {item.quantity}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                )}

                <section className="rp-card overflow-hidden">
                    <h2 className="mb-4 text-sm font-semibold text-rp-text">
                        {t('pages.products.sections.variants')}
                        <span className="ml-2 text-xs font-normal text-rp-text-muted">
                            ({product.variants?.length ?? 0})
                        </span>
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr className="border-b border-rp-border bg-sand-50/80 text-left text-xs font-semibold uppercase tracking-wide text-rp-text-muted dark:bg-ink-900/50">
                                    <th className="px-4 py-3">{t('pages.products.fields.variant')}</th>
                                    <th className="px-4 py-3">SKU</th>
                                    <th className="px-4 py-3">{t('pages.products.sections.identifiers')}</th>
                                    {canShowCost && (
                                        <th className="px-4 py-3 text-right">
                                            {t('pages.products.fields.costPrice')}
                                        </th>
                                    )}
                                    <th className="px-4 py-3 text-right">
                                        {t('pages.products.fields.sellPrice')}
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        {t('pages.products.fields.reorderPoint')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {(product.variants ?? []).map((variant) => (
                                    <tr
                                        key={variant.id}
                                        className="border-b border-rp-border last:border-0"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium text-rp-text">
                                                {variant.name}
                                            </div>
                                            {variant.is_default && (
                                                <span className="text-xs text-rp-text-muted">
                                                    {t('pages.products.useDefaultPrice')}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-rp-text-secondary">
                                            {variant.sku}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-rp-text-secondary">
                                            {variant.barcode ?? '—'}
                                        </td>
                                        {canShowCost && (
                                            <td className="px-4 py-3 text-right font-mono">
                                                {variant.cost_price}
                                            </td>
                                        )}
                                        <td className="px-4 py-3 text-right font-mono">
                                            {variant.sell_price}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            {variant.reorder_point ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                {product.branch_prices?.length > 0 && (
                    <section className="rp-card overflow-hidden">
                        <h2 className="mb-4 text-sm font-semibold text-rp-text">
                            {t('pages.products.sections.branchPricing')}
                        </h2>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-rp-border bg-sand-50/80 text-left text-xs font-semibold uppercase tracking-wide text-rp-text-muted dark:bg-ink-900/50">
                                    <th className="px-4 py-3">{t('common.branch')}</th>
                                    <th className="px-4 py-3 text-right">
                                        {t('pages.products.fields.sellPrice')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {product.branch_prices.map((price) => (
                                    <tr
                                        key={price.branch_id}
                                        className="border-b border-rp-border last:border-0"
                                    >
                                        <td className="px-4 py-3 text-rp-text">
                                            {price.branch_name}
                                        </td>
                                        <td className="px-4 py-3 text-right font-mono">
                                            {price.sell_price}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                )}

                {(product.created_at || product.updated_at) && (
                    <dl className="grid gap-4 text-sm sm:grid-cols-2">
                        {product.created_at && (
                            <DetailField
                                label={t('pages.products.createdAt')}
                                value={new Date(product.created_at).toLocaleString()}
                            />
                        )}
                        {product.updated_at && (
                            <DetailField
                                label={t('pages.products.updatedAt')}
                                value={new Date(product.updated_at).toLocaleString()}
                            />
                        )}
                    </dl>
                )}
            </div>
        </>
    );
}

export default withAdminLayout(Show);
