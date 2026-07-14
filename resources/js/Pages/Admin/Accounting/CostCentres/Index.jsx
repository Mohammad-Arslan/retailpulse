import AdminFormField from '@/Components/common/AdminFormField';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import DataTable from '@/Components/common/DataTable';
import PageHeader from '@/Components/common/PageHeader';
import Modal from '@/Components/Modal';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { useCan } from '@/Hooks/useCan';
import { Head, router, useForm } from '@inertiajs/react';
import { Layers, Plus, SplitSquareVertical } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

function emptyForm() {
    return {
        code: '',
        name: '',
        parent_id: '',
        branch_id: '',
        legal_entity_id: '',
        status: 'active',
        headcount: '',
        floor_area: '',
    };
}

function flattenTree(nodes, depth = 0) {
    return nodes.flatMap((node) => [
        { ...node, depth },
        ...flattenTree(node.children ?? [], depth + 1),
    ]);
}

function Index({
    costCentres = [],
    parentOptions = [],
    branches = [],
    allocatableLines = [],
    allocationMethods = [],
}) {
    const can = useCan();
    const { t } = useTranslation();
    const [modalOpen, setModalOpen] = useState(false);
    const [allocateOpen, setAllocateOpen] = useState(false);
    const [editing, setEditing] = useState(null);

    const confirm = useConfirm();
    const form = useForm(emptyForm());
    const allocateForm = useForm({
        source_journal_transaction_id: '',
        method: 'equal_split',
        targets: [],
        period_from: '',
        period_to: '',
    });
    const flatRows = useMemo(() => flattenTree(costCentres), [costCentres]);

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(emptyForm());
        setModalOpen(true);
    };

    const openEdit = (centre) => {
        setEditing(centre);
        form.clearErrors();
        form.setData({
            code: centre.code ?? '',
            name: centre.name ?? '',
            parent_id: centre.parent_id ? String(centre.parent_id) : '',
            branch_id: centre.branch_id ? String(centre.branch_id) : '',
            legal_entity_id: centre.legal_entity_id ? String(centre.legal_entity_id) : '',
            status: centre.status ?? 'active',
            headcount: centre.headcount != null ? String(centre.headcount) : '',
            floor_area: centre.floor_area != null ? String(centre.floor_area) : '',
        });
        setModalOpen(true);
    };

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setModalOpen(false) };

        if (editing) {
            form.put(route('admin.accounting.cost-centres.update', editing.id), options);
        } else {
            form.post(route('admin.accounting.cost-centres.store'), options);
        }
    };

    const submitAllocate = (e) => {
        e.preventDefault();
        allocateForm
            .transform((data) => ({
                ...data,
                source_journal_transaction_id: Number(data.source_journal_transaction_id),
                targets: (data.targets ?? []).map((id) => ({ cost_centre_id: Number(id) })),
                period_from: data.period_from || null,
                period_to: data.period_to || null,
            }))
            .post(route('admin.accounting.cost-centres.allocate'), {
                preserveScroll: true,
                onSuccess: () => setAllocateOpen(false),
            });
    };

    const deleteCentre = async (centre) => {
        const confirmed = await confirm({
            title: t('common.delete'),
            description: t('pages.accounting.costCentres.confirmDelete'),
            confirmLabel: t('common.delete'),
            variant: 'destructive',
        });

        if (confirmed) {
            router.delete(route('admin.accounting.cost-centres.destroy', centre.id), { preserveScroll: true });
        }
    };

    const parentSelectOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.costCentres.noParent') },
            ...parentOptions.map((p) => ({
                value: String(p.id),
                label: `${p.code} — ${p.name}`,
            })),
        ],
        [parentOptions, t],
    );

    const branchOptions = useMemo(
        () => [
            { value: '', label: t('pages.accounting.chartOfAccounts.allBranches') },
            ...branches.map((b) => ({ value: String(b.id), label: b.name })),
        ],
        [branches, t],
    );

    const lineOptions = useMemo(
        () => allocatableLines.map((line) => ({ value: String(line.id), label: line.label })),
        [allocatableLines],
    );

    const methodOptions = useMemo(
        () =>
            allocationMethods.map((method) => ({
                value: method,
                label: method
                    .split('_')
                    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
                    .join(' '),
            })),
        [allocationMethods],
    );

    const columns = useMemo(
        () => [
            {
                id: 'code',
                header: t('pages.accounting.costCentres.columns.code'),
                cell: ({ row }) => (
                    <div
                        className="flex items-center gap-3"
                        style={{ paddingLeft: `${(row.original.depth ?? 0) * 16}px` }}
                    >
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-500/20 dark:text-violet-300">
                            <Layers className="h-4 w-4" />
                        </span>
                        <span className="font-mono font-medium">{row.original.code}</span>
                    </div>
                ),
            },
            {
                id: 'name',
                accessorKey: 'name',
                header: t('pages.accounting.costCentres.columns.name'),
            },
            {
                id: 'branch_name',
                header: t('pages.accounting.costCentres.columns.branch'),
                cell: ({ row }) => row.original.branch_name ?? '—',
            },
            {
                id: 'headcount',
                header: t('pages.accounting.costCentres.columns.headcount'),
                cell: ({ row }) => row.original.headcount ?? '—',
            },
            {
                id: 'status',
                header: t('common.status'),
                cell: ({ row }) => (
                    <span className="capitalize">{row.original.status}</span>
                ),
            },
        ],
        [t],
    );

    const rowActions = (centre) => {
        if (! can('accounting.manage-cost-centres')) {
            return [];
        }

        return [
            { label: t('common.edit'), type: 'edit', onClick: () => openEdit(centre) },
            { label: t('common.delete'), type: 'delete', onClick: () => deleteCentre(centre) },
        ];
    };

    const toggleTarget = (id) => {
        const current = allocateForm.data.targets ?? [];
        const next = current.includes(id)
            ? current.filter((value) => value !== id)
            : [...current, id];
        allocateForm.setData('targets', next);
    };

    return (
        <>
            <Head title={t('pages.accounting.costCentres.title')} />
            <PageHeader
                title={t('pages.accounting.costCentres.title')}
                description={t('pages.accounting.costCentres.description')}
            >
                {can('accounting.manage-cost-centres') && (
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setAllocateOpen(true)}>
                            <SplitSquareVertical className="h-4 w-4" />
                            {t('pages.accounting.costCentres.allocateAction')}
                        </Button>
                        <Button variant="brand" onClick={openCreate}>
                            <Plus className="h-4 w-4" />
                            {t('pages.accounting.costCentres.createTitle')}
                        </Button>
                    </div>
                )}
            </PageHeader>

            <DataTable
                columns={columns}
                data={flatRows}
                rowActions={rowActions}
                emptyMessage={t('pages.accounting.costCentres.empty')}
            />

            <Modal show={modalOpen} onClose={() => setModalOpen(false)} maxWidth="lg">
                <form onSubmit={submit} className="space-y-4 p-6">
                    <h3 className="text-lg font-semibold">
                        {editing
                            ? t('pages.accounting.costCentres.editTitle', { code: editing.code })
                            : t('pages.accounting.costCentres.createTitle')}
                    </h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField label={t('pages.accounting.costCentres.fields.code')} id="code" error={form.errors.code}>
                            <input
                                id="code"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value)}
                                className="rp-form-input"
                                required
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.costCentres.fields.name')} id="name" error={form.errors.name}>
                            <input
                                id="name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                className="rp-form-input"
                                required
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.costCentres.fields.parent')} id="parent_id" error={form.errors.parent_id}>
                            <Select
                                id="parent_id"
                                value={form.data.parent_id}
                                onChange={(value) => form.setData('parent_id', value ?? '')}
                                options={parentSelectOptions}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.costCentres.fields.branch')} id="branch_id" error={form.errors.branch_id}>
                            <Select
                                id="branch_id"
                                value={form.data.branch_id}
                                onChange={(value) => form.setData('branch_id', value ?? '')}
                                options={branchOptions}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.costCentres.fields.headcount')} id="headcount" error={form.errors.headcount}>
                            <input
                                id="headcount"
                                type="number"
                                min="0"
                                value={form.data.headcount}
                                onChange={(e) => form.setData('headcount', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.accounting.costCentres.fields.floorArea')} id="floor_area" error={form.errors.floor_area}>
                            <input
                                id="floor_area"
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.floor_area}
                                onChange={(e) => form.setData('floor_area', e.target.value)}
                                className="rp-form-input"
                            />
                        </AdminFormField>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={form.processing}>
                            {t('common.save')}
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal show={allocateOpen} onClose={() => setAllocateOpen(false)} maxWidth="lg">
                <form onSubmit={submitAllocate} className="space-y-4 p-6">
                    <h3 className="text-lg font-semibold">
                        {t('pages.accounting.costCentres.allocateTitle')}
                    </h3>
                    {allocateForm.errors.allocation && (
                        <p className="text-sm text-rose-600">{allocateForm.errors.allocation}</p>
                    )}
                    <AdminFormField
                        label={t('pages.accounting.costCentres.fields.sourceLine')}
                        id="source_journal_transaction_id"
                        error={allocateForm.errors.source_journal_transaction_id}
                    >
                        <Select
                            id="source_journal_transaction_id"
                            value={allocateForm.data.source_journal_transaction_id}
                            onChange={(value) => allocateForm.setData('source_journal_transaction_id', value ?? '')}
                            options={[{ value: '', label: '—' }, ...lineOptions]}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.accounting.costCentres.fields.method')}
                        id="method"
                        error={allocateForm.errors.method}
                    >
                        <Select
                            id="method"
                            value={allocateForm.data.method}
                            onChange={(value) => allocateForm.setData('method', value ?? 'equal_split')}
                            options={methodOptions}
                        />
                    </AdminFormField>
                    <div>
                        <p className="mb-2 text-sm font-medium text-rp-text">
                            {t('pages.accounting.costCentres.fields.targets')}
                        </p>
                        <div className="max-h-48 space-y-2 overflow-y-auto rounded-lg border border-rp-border p-3">
                            {flatRows.map((centre) => (
                                <label key={centre.id} className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={(allocateForm.data.targets ?? []).includes(String(centre.id))}
                                        onChange={() => toggleTarget(String(centre.id))}
                                    />
                                    <span className="font-mono">{centre.code}</span>
                                    <span>{centre.name}</span>
                                </label>
                            ))}
                        </div>
                        {allocateForm.errors.targets && (
                            <p className="mt-1 text-sm text-rose-600">{allocateForm.errors.targets}</p>
                        )}
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setAllocateOpen(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" variant="brand" disabled={allocateForm.processing}>
                            {t('pages.accounting.costCentres.allocateSubmit')}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default withAdminLayout(Index);
