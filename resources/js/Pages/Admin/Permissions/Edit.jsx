import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { useConfirm } from '@/Components/common/ConfirmDialogProvider';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Edit({ permission }) {
    const can = useCan();
    const confirm = useConfirm();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, delete: destroy } = useForm({
        display_name: permission.display_name ?? '',
        name: permission.name,
        group: permission.group ?? '',
        description: permission.description ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.permissions.update', permission.id));
    };

    const remove = async () => {
        const confirmed = await confirm({
            title: t('confirm.deleteTitle'),
            description: t('confirm.deletePermission', {
                name: permission.display_name || permission.name,
            }),
            confirmLabel: t('common.delete'),
            cancelLabel: t('confirm.cancel'),
            variant: 'destructive',
        });

        if (confirmed) {
            destroy(route('admin.permissions.destroy', permission.id));
        }
    };

    return (
        <AdminLayout>
            <Head
                title={t('pages.permissions.editTitle', {
                    name: permission.display_name || permission.name,
                })}
            />

            <PageHeader
                title={t('pages.permissions.editTitle', {
                    name: permission.display_name || permission.name,
                })}
                description={permission.name}
            >
                <Link href={route('admin.permissions.index')} className="rp-btn-outline">
                    {t('common.back')}
                </Link>
            </PageHeader>

            <form onSubmit={submit}>
                <FormCard>
                    <AdminFormField
                        label={t('pages.permissions.fields.displayName')}
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
                        label={t('pages.permissions.fields.slug')}
                        id="name"
                        error={errors.name}
                        hint={t('pages.permissions.fields.slugHint')}
                    >
                        <input
                            id="name"
                            value={data.name}
                            className="rp-form-input font-mono text-sm"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.permissions.fields.group')}
                        id="group"
                        error={errors.group}
                    >
                        <input
                            id="group"
                            value={data.group}
                            className="rp-form-input"
                            onChange={(e) => setData('group', e.target.value)}
                        />
                    </AdminFormField>
                    <AdminFormField
                        label={t('pages.permissions.fields.description')}
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
                    <div className="flex flex-wrap gap-2 pt-2">
                        <button type="submit" disabled={processing} className="rp-btn-primary">
                            {t('common.save')}
                        </button>
                        {can('permissions.delete') && (
                            <button
                                type="button"
                                onClick={remove}
                                className="rp-btn-outline border-rose-200 text-rose-500 hover:border-rose-500 hover:bg-rose-100"
                            >
                                {t('pages.permissions.delete')}
                            </button>
                        )}
                    </div>
                </FormCard>
            </form>
        </AdminLayout>
    );
}
