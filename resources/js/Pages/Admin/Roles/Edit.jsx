import PermissionCheckboxes from '@/Components/admin/PermissionCheckboxes';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Edit({ role, permissionGroups }) {
    const can = useCan();
    const confirm = useConfirm();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, delete: destroy } = useForm({
        display_name: role.display_name ?? '',
        name: role.name,
        description: role.description ?? '',
        permissions: [...role.permissions],
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.roles.update', role.id));
    };

    const remove = async () => {
        const confirmed = await confirm({
            title: t('confirm.deleteTitle'),
            description: t('confirm.deleteRole', {
                name: role.display_name || role.name,
            }),
            confirmLabel: t('common.delete'),
            cancelLabel: t('confirm.cancel'),
            variant: 'destructive',
        });

        if (confirmed) {
            destroy(route('admin.roles.destroy', role.id));
        }
    };

    return (
        <AdminLayout>
            <Head title={t('pages.roles.editTitle', { name: role.display_name || role.name })} />

            <PageHeader
                title={t('pages.roles.editTitle', { name: role.display_name || role.name })}
                description={role.description || role.name}
            >
                <Link href={route('admin.roles.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="space-y-5">
                <FormCard>
                    <AdminFormField
                        label={t('pages.roles.fields.displayName')}
                        id="display_name"
                        error={errors.display_name}
                    >
                        <input
                            id="display_name"
                            value={data.display_name}
                            className="rp-form-input"
                            onChange={(e) => setData('display_name', e.target.value)}
                            required
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.roles.fields.slug')}
                        id="name"
                        error={errors.name}
                        hint={
                            role.is_system
                                ? t('pages.roles.fields.slugLocked')
                                : t('pages.roles.fields.slugHint')
                        }
                    >
                        <input
                            id="name"
                            value={data.name}
                            className="rp-form-input font-mono text-sm"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            disabled={role.is_system}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.roles.fields.description')}
                        id="description"
                        error={errors.description}
                    >
                        <input
                            id="description"
                            value={data.description}
                            className="rp-form-input"
                            onChange={(e) => setData('description', e.target.value)}
                        />
                    </AdminFormField>
                </FormCard>

                <div className="rp-card max-w-4xl">
                    <h3 className="rp-form-label mb-3">{t('pages.roles.permissionsTitle')}</h3>
                    <PermissionCheckboxes
                        permissionGroups={permissionGroups}
                        selected={data.permissions}
                        onChange={(permissions) => setData('permissions', permissions)}
                    />
                </div>

                <div className="flex flex-wrap gap-2">
                    <button type="submit" disabled={processing} className="rp-btn-primary">
                        {t('common.save')}
                    </button>
                    {can('roles.delete') && !role.is_system && (
                        <button
                            type="button"
                            onClick={remove}
                            className="rp-btn-outline border-rose-200 text-rose-500 hover:border-rose-500 hover:bg-rose-100"
                        >
                            {t('pages.roles.delete')}
                        </button>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
