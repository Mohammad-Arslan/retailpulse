import AdminFormField from '@/Components/common/AdminFormField';
import Select from '@/Components/ui/select';
import { useCan } from '@/Hooks/useCan';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Boxes, ExternalLink, Plus } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function WarehouseListItem({ warehouse, canEdit }) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col gap-3 rounded-xl border border-rp-border bg-rp-surface-inset/40 p-4 sm:flex-row sm:items-center sm:justify-between dark:bg-white/3">
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-sm font-semibold text-rp-text">{warehouse.name}</span>
                    <span className="rounded-md bg-rp-surface px-2 py-0.5 font-mono text-xs text-rp-text-muted dark:bg-white/6">
                        {warehouse.code}
                    </span>
                    {warehouse.is_default && (
                        <span className="rounded-full bg-teal-500/15 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-teal-600 uppercase dark:text-teal-300">
                            {t('pages.branches.warehouseSection.defaultBadge')}
                        </span>
                    )}
                    {!warehouse.is_active && (
                        <span className="rounded-full bg-ink-200/80 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-rp-text-muted uppercase dark:bg-white/10">
                            {t('pages.branches.inactive')}
                        </span>
                    )}
                </div>
            </div>
            {canEdit && (
                <Link
                    href={route('admin.warehouses.edit', warehouse.id)}
                    className="rp-btn-outline shrink-0 self-start sm:self-center"
                >
                    {t('common.edit')}
                </Link>
            )}
        </div>
    );
}

export default function BranchWarehouseSection({
    mode = 'create',
    branchId = null,
    warehouses = [],
    warehousePicker = [],
    initialWarehouseId = '',
    defaultWarehouseId = '',
    warehouseOptions = [],
    errors = {},
    onInitialWarehouseChange,
    onDefaultWarehouseChange,
}) {
    const can = useCan();
    const { t } = useTranslation();
    const canManageWarehouses = can('warehouses.view');
    const canCreateWarehouse = can('warehouses.create');
    const canEditWarehouse = can('warehouses.update');

    const pickerOptions = useMemo(
        () =>
            warehousePicker.map((warehouse) => ({
                value: String(warehouse.id),
                label: warehouse.label,
            })),
        [warehousePicker],
    );

    const selectedTemplate = useMemo(
        () => warehousePicker.find((warehouse) => String(warehouse.id) === String(initialWarehouseId)),
        [warehousePicker, initialWarehouseId],
    );

    const activeWarehouseOptions = warehouseOptions.filter((option) => option.value);

    const warehousesIndexUrl = branchId
        ? `${route('admin.warehouses.index')}?branch_id=${branchId}`
        : route('admin.warehouses.index');

    const createWarehouseUrl = branchId
        ? `${route('admin.warehouses.create')}?branch_id=${branchId}`
        : route('admin.warehouses.create');

    return (
        <section className="rp-card w-full space-y-0 overflow-hidden p-0">
            <div className="flex flex-col gap-4 border-b border-rp-border px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6 lg:px-8">
                <div className="flex min-w-0 items-start gap-3">
                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-teal-500/15 text-teal-600 dark:text-teal-300">
                        <Boxes className="h-5 w-5" />
                    </span>
                    <div className="min-w-0">
                        <h3 className="rp-form-label text-base">
                            {t('pages.branches.sections.warehouse')}
                        </h3>
                        <p className="mt-1 max-w-3xl text-sm leading-relaxed text-rp-text-muted">
                            {mode === 'create'
                                ? t('pages.branches.warehouseSection.createHint')
                                : t('pages.branches.warehouseSection.editHint')}
                        </p>
                    </div>
                </div>

                {canManageWarehouses && (
                    <div className="flex flex-wrap gap-2 sm:shrink-0">
                        {canCreateWarehouse && (
                            <Link href={createWarehouseUrl} className="rp-btn-outline">
                                <Plus className="h-4 w-4" />
                                {t('common.addWarehouse')}
                            </Link>
                        )}
                        {mode === 'edit' && (
                            <Link href={warehousesIndexUrl} className="rp-btn-outline">
                                <ExternalLink className="h-4 w-4" />
                                {t('pages.branches.warehouseSection.manageAll')}
                            </Link>
                        )}
                    </div>
                )}
            </div>

            <div className="space-y-5 px-5 py-5 sm:px-6 lg:px-8">
                {mode === 'create' ? (
                    <>
                        <div className="rounded-xl border border-teal-500/20 bg-teal-500/5 px-4 py-3 text-sm text-rp-text-secondary dark:border-teal-500/25 dark:bg-teal-500/10">
                            {pickerOptions.length > 0
                                ? t('pages.branches.warehouseSection.createPickerInfo')
                                : t('pages.branches.warehouseSection.createInfo')}
                        </div>

                        {pickerOptions.length > 0 ? (
                            <div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
                                <div className="xl:col-span-12">
                                    <AdminFormField
                                        label={t('pages.branches.fields.defaultWarehouse')}
                                        id="initial_warehouse_id"
                                        error={errors.initial_warehouse_id}
                                    >
                                        <Select
                                            id="initial_warehouse_id"
                                            options={pickerOptions}
                                            value={initialWarehouseId}
                                            onChange={onInitialWarehouseChange}
                                            placeholder={t('pages.branches.warehouseSection.selectTemplate')}
                                            isSearchable
                                        />
                                    </AdminFormField>
                                </div>
                                {selectedTemplate && (
                                    <div className="xl:col-span-12">
                                        <div className="rounded-xl border border-rp-border bg-rp-surface-inset/50 px-4 py-3 dark:bg-white/4">
                                            <p className="text-xs font-semibold tracking-widest text-rp-text-muted uppercase">
                                                {t('pages.branches.warehouseSection.templateDetails')}
                                            </p>
                                            <p className="mt-1 text-sm text-rp-text">
                                                {selectedTemplate.name}
                                                <span className="mx-2 text-rp-text-muted">·</span>
                                                <span className="font-mono text-xs text-rp-text-secondary">
                                                    {selectedTemplate.branch_code}
                                                </span>
                                            </p>
                                            <p className="mt-1 text-xs text-rp-text-muted">
                                                {t('pages.branches.warehouseSection.templateCodeHint', {
                                                    code: selectedTemplate.code,
                                                })}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="rounded-xl border border-dashed border-rp-border px-4 py-4 text-sm text-rp-text-muted">
                                {t('pages.branches.warehouseSection.noPickerWarehouses')}
                            </div>
                        )}

                        <div className="flex items-center gap-2 rounded-lg border border-dashed border-rp-border px-4 py-3 text-sm text-rp-text-muted">
                            <span className="h-2 w-2 rounded-full bg-teal-400" />
                            {t('pages.branches.warehouseSection.defaultOnCreate')}
                        </div>
                    </>
                ) : (
                    <>
                        <div className="grid grid-cols-1 gap-4">
                            <AdminFormField
                                label={t('pages.branches.fields.defaultWarehouse')}
                                id="default_warehouse_id"
                                error={errors.default_warehouse_id}
                            >
                                {activeWarehouseOptions.length > 0 ? (
                                    <Select
                                        id="default_warehouse_id"
                                        options={activeWarehouseOptions}
                                        value={defaultWarehouseId}
                                        onChange={onDefaultWarehouseChange}
                                        placeholder={t('pages.branches.warehouseSection.selectDefault')}
                                        isSearchable
                                    />
                                ) : (
                                    <div className="rounded-xl border border-dashed border-rp-border px-4 py-4 text-sm text-rp-text-muted">
                                        {t('pages.branches.warehouseSection.noWarehouses')}
                                        {canCreateWarehouse && (
                                            <>
                                                {' '}
                                                <Link
                                                    href={createWarehouseUrl}
                                                    className="font-medium text-teal-600 hover:underline dark:text-teal-300"
                                                >
                                                    {t('common.addWarehouse')}
                                                </Link>
                                            </>
                                        )}
                                    </div>
                                )}
                            </AdminFormField>
                        </div>

                        {warehouses.length > 0 && (
                            <div className="space-y-3">
                                <p className="text-xs font-semibold tracking-widest text-rp-text-muted uppercase">
                                    {t('pages.branches.warehouseSection.branchWarehouses')}
                                </p>
                                <div
                                    className={cn(
                                        'grid grid-cols-1 gap-3',
                                        warehouses.length > 1 && 'lg:grid-cols-2',
                                    )}
                                >
                                    {warehouses.map((warehouse) => (
                                        <WarehouseListItem
                                            key={warehouse.id}
                                            warehouse={warehouse}
                                            canEdit={canEditWarehouse}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>
        </section>
    );
}
