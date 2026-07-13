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
import { Layers, Plus } from 'lucide-react';
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
    };
}

function flattenTree(nodes, depth = 0) {
    return nodes.flatMap((node) => [
        { ...node, depth },
        ...flattenTree(node.children ?? [], depth + 1),
    ]);
}

function Index({ costCentres = [], parentOptions = [], branches = [] }) {
    const can = useCan();
    const { t } = useTranslation();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);

    const confirm = useConfirm();
    const form = useForm(emptyForm());
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
        if (!can('accounting.manage-cost-centres')) {
            return [];
        }

        return [
            { label: t('common.edit'), type: 'edit', onClick: () => openEdit(centre) },
            { label: t('common.delete'), type: 'delete', onClick: () => deleteCentre(centre) },
        ];
    };

    return (
        <>
            <Head title={t('pages.accounting.costCentres.title')} />
            <PageHeader
                title={t('pages.accounting.costCentres.title')}
                description={t('pages.accounting.costCentres.description')}
            >
                {can('accounting.manage-cost-centres') && (
                    <Button variant="brand" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        {t('pages.accounting.costCentres.createTitle')}
                    </Button>
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
        </>
    );
}

export default withAdminLayout(Index);
