import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function SectionTitle({ children }) {
    return <h2 className="text-sm font-semibold text-rp-text">{children}</h2>;
}

function Create({ branches = [], scopeTypes = [], scopeModes = [] }) {
    const { t } = useTranslation();

    const scopeTypeOptions = useMemo(
        () => scopeTypes.map((value) => ({ value, label: t(`pages.loyalty.programs.scopeTypes.${value}`) })),
        [scopeTypes, t],
    );
    const scopeModeOptions = useMemo(
        () => scopeModes.map((value) => ({ value, label: t(`pages.loyalty.programs.scopeModes.${value}`) })),
        [scopeModes, t],
    );

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        scope_type: scopeTypes[0] ?? 'global',
        earn_scope: scopeModes[0] ?? 'global',
        redeem_scope: scopeModes[0] ?? 'global',
        allow_cross_branch_earn: true,
        allow_cross_branch_redeem: true,
        starts_at: '',
        ends_at: '',
        branch_ids: [],
    });

    const showBranches = data.scope_type !== 'global';

    function toggleBranch(id) {
        setData(
            'branch_ids',
            data.branch_ids.includes(id)
                ? data.branch_ids.filter((b) => b !== id)
                : [...data.branch_ids, id],
        );
    }

    function submit(e) {
        e.preventDefault();
        post(route('admin.loyalty.programs.store'));
    }

    return (
        <>
            <Head title={t('pages.loyalty.programs.createTitle')} />
            <PageHeader title={t('pages.loyalty.programs.createTitle')} description={t('pages.loyalty.programs.description')}>
                <Link href={route('admin.loyalty.programs.index')} className="rp-btn-outline">
                    {t('confirm.cancel')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="max-w-3xl space-y-5">
                <FormCard className="max-w-none">
                    <SectionTitle>{t('pages.loyalty.programs.sections.details')}</SectionTitle>
                    <AdminFormField label={t('pages.loyalty.programs.fields.name')} id="name" error={errors.name}>
                        <input
                            id="name"
                            value={data.name}
                            className="rp-form-input"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.loyalty.programs.fields.description')}
                        id="description"
                        error={errors.description}
                    >
                        <textarea
                            id="description"
                            rows={3}
                            value={data.description}
                            className="rp-form-input"
                            onChange={(e) => setData('description', e.target.value)}
                        />
                    </AdminFormField>
                </FormCard>

                <FormCard className="max-w-none">
                    <SectionTitle>{t('pages.loyalty.programs.sections.scope')}</SectionTitle>
                    <AdminFormField
                        label={t('pages.loyalty.programs.fields.scopeType')}
                        id="scope_type"
                        hint={t('pages.loyalty.programs.hints.scopeType')}
                        error={errors.scope_type}
                    >
                        <Select
                            id="scope_type"
                            options={scopeTypeOptions}
                            value={data.scope_type}
                            onChange={(value) => setData('scope_type', value ?? 'global')}
                        />
                    </AdminFormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField
                            label={t('pages.loyalty.programs.fields.earnScope')}
                            id="earn_scope"
                            hint={t('pages.loyalty.programs.hints.earnScope')}
                            error={errors.earn_scope}
                        >
                            <Select
                                id="earn_scope"
                                options={scopeModeOptions}
                                value={data.earn_scope}
                                onChange={(value) => setData('earn_scope', value ?? 'global')}
                            />
                        </AdminFormField>
                        <AdminFormField
                            label={t('pages.loyalty.programs.fields.redeemScope')}
                            id="redeem_scope"
                            hint={t('pages.loyalty.programs.hints.redeemScope')}
                            error={errors.redeem_scope}
                        >
                            <Select
                                id="redeem_scope"
                                options={scopeModeOptions}
                                value={data.redeem_scope}
                                onChange={(value) => setData('redeem_scope', value ?? 'global')}
                            />
                        </AdminFormField>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="flex items-center gap-2 text-sm text-rp-text-secondary">
                            <input
                                type="checkbox"
                                checked={data.allow_cross_branch_earn}
                                onChange={(e) => setData('allow_cross_branch_earn', e.target.checked)}
                            />
                            {t('pages.loyalty.programs.fields.allowCrossBranchEarn')}
                        </label>
                        <label className="flex items-center gap-2 text-sm text-rp-text-secondary">
                            <input
                                type="checkbox"
                                checked={data.allow_cross_branch_redeem}
                                onChange={(e) => setData('allow_cross_branch_redeem', e.target.checked)}
                            />
                            {t('pages.loyalty.programs.fields.allowCrossBranchRedeem')}
                        </label>
                    </div>

                    {showBranches && (
                        <AdminFormField
                            label={t('pages.loyalty.programs.fields.branches')}
                            id="branch_ids"
                            hint={t('pages.loyalty.programs.hints.branches')}
                            error={errors.branch_ids}
                        >
                            <div className="grid gap-2 rounded-lg border border-rp-border p-3 sm:grid-cols-2">
                                {branches.map((branch) => (
                                    <label key={branch.id} className="flex items-center gap-2 text-sm text-rp-text-secondary">
                                        <input
                                            type="checkbox"
                                            checked={data.branch_ids.includes(branch.id)}
                                            onChange={() => toggleBranch(branch.id)}
                                        />
                                        {branch.name}
                                    </label>
                                ))}
                            </div>
                        </AdminFormField>
                    )}
                </FormCard>

                <FormCard className="max-w-none">
                    <SectionTitle>{t('pages.loyalty.programs.sections.schedule')}</SectionTitle>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <AdminFormField label={t('pages.loyalty.programs.fields.startsAt')} id="starts_at" error={errors.starts_at}>
                            <input
                                id="starts_at"
                                type="datetime-local"
                                value={data.starts_at}
                                className="rp-form-input"
                                onChange={(e) => setData('starts_at', e.target.value)}
                            />
                        </AdminFormField>
                        <AdminFormField label={t('pages.loyalty.programs.fields.endsAt')} id="ends_at" error={errors.ends_at}>
                            <input
                                id="ends_at"
                                type="datetime-local"
                                value={data.ends_at}
                                className="rp-form-input"
                                onChange={(e) => setData('ends_at', e.target.value)}
                            />
                        </AdminFormField>
                    </div>
                </FormCard>

                <button type="submit" disabled={processing} className="rp-btn-primary">
                    {t('common.save')}
                </button>
            </form>
        </>
    );
}

export default withAdminLayout(Create);
