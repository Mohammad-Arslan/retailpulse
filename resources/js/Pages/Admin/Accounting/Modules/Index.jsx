import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import Select from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

function dependentsOf(moduleKey, catalog) {
    return catalog
        .filter((m) => (m.requires ?? []).includes(moduleKey))
        .map((m) => m.key);
}

function allDependents(moduleKey, catalog, collected = new Set()) {
    for (const dep of dependentsOf(moduleKey, catalog)) {
        if (collected.has(dep)) continue;
        collected.add(dep);
        allDependents(dep, catalog, collected);
    }
    return collected;
}

function allRequires(moduleKey, catalog, collected = new Set()) {
    const mod = catalog.find((m) => m.key === moduleKey);
    for (const req of mod?.requires ?? []) {
        if (collected.has(req)) continue;
        collected.add(req);
        allRequires(req, catalog, collected);
    }
    return collected;
}

function Index({
    branches = [],
    selectedBranchId = null,
    modules: catalog = [],
    enabledModules = ['core'],
    requiresBranchSelection = false,
}) {
    const { t } = useTranslation();

    const { data, setData, put, processing, errors } = useForm({
        branch_id: selectedBranchId ? String(selectedBranchId) : '',
        modules: enabledModules,
    });

    const branchOptions = useMemo(
        () =>
            branches.map((b) => ({
                value: String(b.id),
                label: b.code ? `${b.name} (${b.code})` : b.name,
            })),
        [branches],
    );

    const moduleLabel = (key) =>
        t(`common.accountingModules.${key}`, {
            defaultValue: key,
        });

    const onBranchChange = (branchId) => {
        router.get(
            route('admin.accounting.modules.index'),
            { branch_id: branchId || undefined },
            { preserveState: false },
        );
    };

    const isChecked = (key) => data.modules.includes(key);

    const toggleModule = (key, checked) => {
        const mod = catalog.find((m) => m.key === key);
        if (!mod || mod.always_enabled) {
            return;
        }

        const next = new Set(data.modules);
        next.add('core');

        if (checked) {
            next.add(key);
            for (const req of allRequires(key, catalog)) {
                next.add(req);
            }
        } else {
            next.delete(key);
            for (const dep of allDependents(key, catalog)) {
                next.delete(dep);
            }
        }

        setData('modules', Array.from(next));
    };

    const submit = (e) => {
        e.preventDefault();
        if (!data.branch_id) return;

        put(route('admin.accounting.modules.update'), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={t('pages.accounting.modules.title')} />
            <PageHeader
                title={t('pages.accounting.modules.title')}
                description={t('pages.accounting.modules.description')}
            />

            <FormCard className="mb-6">
                <AdminFormField
                    label={t('common.branch')}
                    id="branch_id"
                    error={errors.branch_id}
                >
                    <Select
                        id="branch_id"
                        options={branchOptions}
                        value={data.branch_id}
                        onChange={onBranchChange}
                        placeholder={t('common.selectBranch')}
                    />
                </AdminFormField>
                {requiresBranchSelection && (
                    <p className="mt-3 text-sm text-amber-700 dark:text-amber-400">
                        {t('pages.accounting.modules.selectBranchHint')}
                    </p>
                )}
            </FormCard>

            {!requiresBranchSelection && selectedBranchId && (
                <form onSubmit={submit} className="space-y-5">
                    <FormCard title={t('pages.accounting.modules.modulesTitle')}>
                        <p className="mb-4 text-sm text-muted-foreground">
                            {t('pages.accounting.modules.modulesHint')}
                        </p>
                        <ul className="space-y-3">
                            {catalog.map((mod) => {
                                const checked = isChecked(mod.key);
                                const requiresLabels = (mod.requires ?? [])
                                    .filter((r) => r !== 'core')
                                    .map((r) => moduleLabel(r));

                                return (
                                    <li
                                        key={mod.key}
                                        className="flex items-start gap-3 rounded-lg border border-border/60 px-3 py-3"
                                    >
                                        <input
                                            id={`module-${mod.key}`}
                                            type="checkbox"
                                            className="mt-1 h-4 w-4 rounded border-input"
                                            checked={checked}
                                            disabled={mod.always_enabled || processing}
                                            onChange={(e) => toggleModule(mod.key, e.target.checked)}
                                        />
                                        <label
                                            htmlFor={`module-${mod.key}`}
                                            className="flex-1 cursor-pointer space-y-0.5"
                                        >
                                            <div className="text-sm font-medium">
                                                {moduleLabel(mod.key)}
                                                {mod.always_enabled && (
                                                    <span className="ms-2 text-xs font-normal text-muted-foreground">
                                                        ({t('pages.accounting.modules.alwaysOn')})
                                                    </span>
                                                )}
                                            </div>
                                            {requiresLabels.length > 0 && (
                                                <div className="text-xs text-muted-foreground">
                                                    {t('pages.accounting.modules.requires', {
                                                        modules: requiresLabels.join(', '),
                                                    })}
                                                </div>
                                            )}
                                        </label>
                                    </li>
                                );
                            })}
                        </ul>
                        {errors.modules && (
                            <p className="mt-3 text-sm text-destructive">{errors.modules}</p>
                        )}
                    </FormCard>

                    <div className="flex justify-end">
                        <Button type="submit" variant="brand" disabled={processing || !data.branch_id}>
                            {t('pages.accounting.modules.save')}
                        </Button>
                    </div>
                </form>
            )}
        </>
    );
}

export default withAdminLayout(Index);
