import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { useWarehouseCodeSuggestion } from '@/Hooks/useWarehouseCodeSuggestion';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

export default function Create({ branches, defaultBranchId = null }) {
    const { t } = useTranslation();
    const initialBranchId =
        defaultBranchId !== null
            ? String(defaultBranchId)
            : branches.length === 1
              ? String(branches[0].id)
              : '';

    const branchOptions = useMemo(
        () =>
            branches.map((branch) => ({
                value: String(branch.id),
                label: `${branch.name} (${branch.code})`,
            })),
        [branches],
    );

    const { data, setData, post, processing, errors } = useForm({
        branch_id: initialBranchId,
        name: '',
        is_default: false,
    });

    const { suggestedCode, isPreview, loading } = useWarehouseCodeSuggestion({
        name: data.name,
        branchId: data.branch_id,
        enabled: Boolean(data.name?.trim()),
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.warehouses.store'));
    };

    return (
        <AdminLayout>
            <Head title={t('pages.warehouses.createTitle')} />
            <PageHeader title={t('pages.warehouses.createTitle')}>
                <Link href={route('admin.warehouses.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>
            <form onSubmit={submit} className="w-full space-y-5">
                <FormCard className="max-w-none w-full">
                    <AdminFormField
                        label={t('pages.warehouses.fields.branch')}
                        id="branch_id"
                        error={errors.branch_id}
                    >
                        {branches.length > 1 ? (
                            <Select
                                id="branch_id"
                                options={branchOptions}
                                value={data.branch_id}
                                onChange={(value) => setData('branch_id', value)}
                                placeholder={t('pages.warehouses.selectBranch')}
                            />
                        ) : (
                            <input
                                id="branch_id"
                                value={
                                    branches[0]
                                        ? `${branches[0].name} (${branches[0].code})`
                                        : ''
                                }
                                className="rp-form-input w-full bg-rp-surface-inset"
                                disabled
                                readOnly
                            />
                        )}
                    </AdminFormField>

                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-12">
                        <div className="xl:col-span-6">
                            <AdminFormField
                                label={t('pages.warehouses.fields.name')}
                                id="name"
                                error={errors.name}
                            >
                                <input
                                    id="name"
                                    value={data.name}
                                    className="rp-form-input w-full"
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                            </AdminFormField>
                        </div>
                        <div className="xl:col-span-6">
                            {suggestedCode && (
                                <div className="rounded-xl border border-rp-border bg-rp-surface-inset/50 px-4 py-3 dark:bg-white/4">
                                    <p className="text-xs font-semibold tracking-widest text-rp-text-muted uppercase">
                                        {t('pages.warehouses.fields.code')}
                                    </p>
                                    <p className="mt-1 font-mono text-sm font-semibold text-rp-text">
                                        {suggestedCode}
                                    </p>
                                    <p className="mt-1 text-xs text-rp-text-muted">
                                        {loading
                                            ? t('pages.warehouses.codeGenerating')
                                            : isPreview
                                              ? t('pages.warehouses.codePreviewHint')
                                              : t('pages.warehouses.codeUniqueHint')}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-rp-text-secondary">
                        <input
                            type="checkbox"
                            checked={data.is_default}
                            onChange={(e) => setData('is_default', e.target.checked)}
                        />
                        {t('pages.warehouses.fields.default')}
                    </label>
                </FormCard>
                <button type="submit" disabled={processing} className="rp-btn-primary">
                    {t('pages.warehouses.createSubmit')}
                </button>
            </form>
        </AdminLayout>
    );
}
