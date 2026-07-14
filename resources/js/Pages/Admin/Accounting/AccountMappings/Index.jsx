import AdminFormField from '@/Components/common/AdminFormField';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { mappingKeyLabel } from '@/lib/accountingI18n';
import { paymentMethodLabel } from '@/lib/procurementI18n';
import { Head, router, useForm } from '@inertiajs/react';
import { Link2, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyMappingForm() {
    return {
        mapping_key: '',
        account_id: '',
        branch_id: '',
        warehouse_id: '',
        product_category_id: '',
        payment_method: '',
        currency_code: '',
        legal_entity_id: '',
        effective_from: '',
        effective_to: '',
        priority: '100',
        status: 'active',
    };
}

function Index({
    mappings,
    filters,
    mappingKeys = [],
    accounts = [],
    branches = [],
    warehouses = [],
    categories = [],
    paymentMethods = [],
    currencies = [],
    legalEntities = [],
}) {
    const can = useCan();
    const { t } = useTranslation();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);

    const form = useForm(emptyMappingForm());

    const search = (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        router.get(route('admin.accounting.account-mappings.index'), Object.fromEntries(formData), {
            preserveState: true,
        });
    };

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(emptyMappingForm());
        setModalOpen(true);
    };

    const openEdit = (mapping) => {
        setEditing(mapping);
        form.clearErrors();
        form.setData({
            mapping_key: mapping.mapping_key ?? '',
            account_id: mapping.account_id ? String(mapping.account_id) : '',
            branch_id: mapping.branch_id ? String(mapping.branch_id) : '',
            warehouse_id: mapping.warehouse_id ? String(mapping.warehouse_id) : '',
            product_category_id: mapping.product_category_id ? String(mapping.product_category_id) : '',
            payment_method: mapping.payment_method ?? '',
            currency_code: mapping.currency_code ?? '',
            legal_entity_id: mapping.legal_entity_id ? String(mapping.legal_entity_id) : '',
            effective_from: mapping.effective_from?.slice(0, 10) ?? '',
            effective_to: mapping.effective_to?.slice(0, 10) ?? '',
            priority: String(mapping.priority ?? 100),
            status: mapping.status ?? 'active',
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditing(null);
    };

    const submitMapping = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => closeModal() };

        const payload = {
            ...form.data,
            branch_id: form.data.branch_id || null,
            warehouse_id: form.data.warehouse_id || null,
            product_category_id: form.data.product_category_id || null,
            payment_method: form.data.payment_method || null,
            currency_code: form.data.currency_code || null,
            legal_entity_id: form.data.legal_entity_id || null,
            effective_from: form.data.effective_from || null,
            effective_to: form.data.effective_to || null,
        };

        form.transform(() => payload);

        if (editing) {
            form.put(route('admin.accounting.account-mappings.update', editing.id), options);
        } else {
            form.post(route('admin.accounting.account-mappings.store'), options);
        }
    };

    const mappingKeyOptions = useMemo(
        () =>
            mappingKeys.map((key) => ({
                value: key,
                label: mappingKeyLabel(t, key),
            })),
        [mappingKeys, t],
    );

    const accountOptions = useMemo(
        () =>
            accounts.map((a) => ({
                value: String(a.id),
                label: `${a.code} — ${a.name}`,
            })),
        [accounts],
    );

    const branchOptions = useMemo(
        () => [
            { value: '', label: t('common.allBranches') },
            ...branches.map((b) => ({ value: String(b.id), label: b.name })),
        ],
        [branches, t],
    );

    const warehouseOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.accountMappings.allWarehouses') },
            ...warehouses.map((w) => ({
                value: String(w.id),
                label: w.code ? `${w.code} — ${w.name}` : w.name,
            })),
        ],
        [warehouses, t],
    );

    const categoryOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.accountMappings.allCategories') },
            ...categories.map((c) => ({ value: String(c.id), label: c.name })),
        ],
        [categories, t],
    );

    const paymentMethodOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.accountMappings.allPaymentMethods') },
            ...paymentMethods.map((method) => ({
                value: method,
                label: paymentMethodLabel(t, method),
            })),
        ],
        [paymentMethods, t],
    );

    const currencyOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.accountMappings.allCurrencies') },
            ...currencies.map((c) => ({
                value: c.code,
                label: `${c.code} — ${c.name}`,
            })),
        ],
        [currencies, t],
    );

    const legalEntityOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.accountMappings.allLegalEntities') },
            ...legalEntities.map((entity) => ({
                value: String(entity.id),
                label: entity.legal_name,
            })),
        ],
        [legalEntities, t],
    );

    const columns = useMemo(
        () => [
            {
                id: 'mapping_key',
                header: t('pages.accounting.accountMappings.columns.key'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300">
                            <Link2 className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold text-rp-text">
                                {mappingKeyLabel(t, row.original.mapping_key)}
                            </div>
                            <div className="font-mono text-xs text-rp-text-muted">
                                {row.original.mapping_key}
                            </div>
                        </div>
                    </div>
                ),
            },
            {
                id: 'account',
                header: t('pages.accounting.accountMappings.columns.account'),
                cell: ({ row }) => (
                    <div>
                        <div className="text-sm font-medium">{row.original.account?.name ?? '—'}</div>
                        <div className="text-xs text-rp-text-muted">{row.original.account?.code}</div>
                    </div>
                ),
            },
            {
                id: 'branch',
                header: t('common.branch'),
                cell: ({ row }) => row.original.branch?.name ?? t('common.allBranches'),
            },
            {
                id: 'scope',
                header: t('pages.accounting.accountMappings.columns.scope'),
                cell: ({ row }) => {
                    const bits = [];
                    if (row.original.warehouse_id) {
                        bits.push(t('pages.accounting.accountMappings.scopeWarehouse'));
                    }
                    if (row.original.payment_method) {
                        bits.push(paymentMethodLabel(t, row.original.payment_method));
                    }
                    if (row.original.currency_code) {
                        bits.push(row.original.currency_code);
                    }
                    return bits.length > 0 ? bits.join(' · ') : '—';
                },
            },
            {
                id: 'priority',
                header: t('pages.accounting.accountMappings.columns.priority'),
                cell: ({ row }) => row.original.priority ?? '—',
            },
            {
                id: 'status',
                header: t('common.status'),
                cell: ({ row }) => (
                    <span
                        className={`text-xs font-medium ${row.original.status === 'active' ? 'text-teal-600' : 'text-rp-text-muted'}`}
                    >
                        {row.original.status === 'active' ? t('common.active') : t('common.inactive')}
                    </span>
                ),
            },
        ],
        [t],
    );

    const rowActions = (mapping) => {
        if (!can('accounting.manage-mappings')) {
            return [];
        }

        return [
            {
                label: t('common.edit'),
                type: 'edit',
                onClick: () => openEdit(mapping),
                permission: 'accounting.manage-mappings',
            },
        ];
    };

    return (
        <>
            <Head title={t('pages.accounting.accountMappings.title')} />
            <PageHeader
                title={t('pages.accounting.accountMappings.title')}
                description={t('pages.accounting.accountMappings.description')}
            >
                {can('accounting.manage-mappings') && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.accounting.accountMappings.createTitle')}
                    </Button>
                )}
            </PageHeader>

            <form onSubmit={search} className="rp-filter-bar flex-wrap gap-2">
                <div className="rp-search-inset min-w-[200px] flex-1">
                    <Search className="h-3.5 w-3.5 shrink-0 text-rp-text-muted" />
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder={t('pages.accounting.accountMappings.searchPlaceholder')}
                        className="rp-search-input"
                    />
                </div>
                <Select
                    name="mapping_key"
                    defaultValue={filters.mapping_key ?? ''}
                    className="w-auto min-w-[12rem]"
                    options={[
                        { value: '', label: t('pages.accounting.accountMappings.allKeys') },
                        ...mappingKeyOptions,
                    ]}
                />
                <button type="submit" className="rp-btn-outline">
                    {t('common.search')}
                </button>
            </form>

            <DataTable
                columns={columns}
                data={mappings.data}
                pagination={mappings}
                filters={filters}
                indexRoute="admin.accounting.account-mappings.index"
                rowActions={rowActions}
                emptyMessage={t('pages.accounting.accountMappings.empty')}
            />

            <Modal show={modalOpen} onClose={closeModal} maxWidth="2xl">
                <form onSubmit={submitMapping} className="p-6">
                    <h2 className="text-lg font-semibold">
                        {editing
                            ? t('pages.accounting.accountMappings.editTitle')
                            : t('pages.accounting.accountMappings.createTitle')}
                    </h2>

                    <div className="mt-5 grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.key')}
                            id="mapping_key"
                            error={form.errors.mapping_key}
                        >
                            <Select
                                id="mapping_key"
                                value={form.data.mapping_key}
                                onChange={(value) => form.setData('mapping_key', value ?? '')}
                                options={[
                                    { value: '', label: t('pages.accounting.accountMappings.selectKey') },
                                    ...mappingKeyOptions,
                                ]}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.account')}
                            id="account_id"
                            error={form.errors.account_id}
                        >
                            <Select
                                id="account_id"
                                value={form.data.account_id}
                                onChange={(value) => form.setData('account_id', value ?? '')}
                                options={[
                                    { value: '', label: t('pages.accounting.accountMappings.selectAccount') },
                                    ...accountOptions,
                                ]}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('common.branch')}
                            id="branch_id"
                            error={form.errors.branch_id}
                        >
                            <Select
                                id="branch_id"
                                value={form.data.branch_id}
                                onChange={(value) => form.setData('branch_id', value ?? '')}
                                options={branchOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.warehouse')}
                            id="warehouse_id"
                            error={form.errors.warehouse_id}
                        >
                            <Select
                                id="warehouse_id"
                                value={form.data.warehouse_id}
                                onChange={(value) => form.setData('warehouse_id', value ?? '')}
                                options={warehouseOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.category')}
                            id="product_category_id"
                            error={form.errors.product_category_id}
                        >
                            <Select
                                id="product_category_id"
                                value={form.data.product_category_id}
                                onChange={(value) => form.setData('product_category_id', value ?? '')}
                                options={categoryOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.paymentMethod')}
                            id="payment_method"
                            error={form.errors.payment_method}
                        >
                            <Select
                                id="payment_method"
                                value={form.data.payment_method}
                                onChange={(value) => form.setData('payment_method', value ?? '')}
                                options={paymentMethodOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.currency')}
                            id="currency_code"
                            error={form.errors.currency_code}
                        >
                            <Select
                                id="currency_code"
                                value={form.data.currency_code}
                                onChange={(value) => form.setData('currency_code', value ?? '')}
                                options={currencyOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.legalEntity')}
                            id="legal_entity_id"
                            error={form.errors.legal_entity_id}
                        >
                            <Select
                                id="legal_entity_id"
                                value={form.data.legal_entity_id}
                                onChange={(value) => form.setData('legal_entity_id', value ?? '')}
                                options={legalEntityOptions}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.effectiveFrom')}
                            id="effective_from"
                            error={form.errors.effective_from}
                        >
                            <input
                                id="effective_from"
                                type="date"
                                value={form.data.effective_from}
                                onChange={(e) => form.setData('effective_from', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.effectiveTo')}
                            id="effective_to"
                            error={form.errors.effective_to}
                        >
                            <input
                                id="effective_to"
                                type="date"
                                value={form.data.effective_to}
                                onChange={(e) => form.setData('effective_to', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.accounting.accountMappings.fields.priority')}
                            id="priority"
                            error={form.errors.priority}
                        >
                            <input
                                id="priority"
                                type="number"
                                min="0"
                                value={form.data.priority}
                                onChange={(e) => form.setData('priority', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('common.status')}
                            id="status"
                            error={form.errors.status}
                        >
                            <Select
                                id="status"
                                value={form.data.status}
                                onChange={(value) => form.setData('status', value ?? 'active')}
                                options={[
                                    { value: 'active', label: t('common.active') },
                                    { value: 'inactive', label: t('common.inactive') },
                                ]}
                            />
                        </AdminFormField>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={closeModal}>
                            {t('common.back')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {t('common.save')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
