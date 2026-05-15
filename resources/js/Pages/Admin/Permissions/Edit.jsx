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
            description: t('confirm.deletePermission', { name: permission.name }),
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
            <Head title="Edit permission" />

            <PageHeader title="Edit Permission" description={permission.name}>
                <Link
                    href={route('admin.permissions.index')}
                    className="rp-btn-outline"
                >
                    Back
                </Link>
            </PageHeader>

            <form onSubmit={submit}>
                <FormCard>
                    <AdminFormField label="Name" id="name" error={errors.name}>
                        <input
                            id="name"
                            value={data.name}
                            className="rp-form-input"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                    </AdminFormField>
                    <AdminFormField label="Group" id="group">
                        <input
                            id="group"
                            value={data.group}
                            className="rp-form-input"
                            onChange={(e) => setData('group', e.target.value)}
                        />
                    </AdminFormField>
                    <AdminFormField label="Description" id="description">
                        <input
                            id="description"
                            value={data.description}
                            className="rp-form-input"
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                        />
                    </AdminFormField>
                    <div className="flex flex-wrap gap-2 pt-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rp-btn-primary"
                        >
                            Save changes
                        </button>
                        {can('permissions.delete') && (
                            <button
                                type="button"
                                onClick={remove}
                                className="rp-btn-outline border-rose-200 text-rose-500 hover:border-rose-500 hover:bg-rose-100"
                            >
                                Delete permission
                            </button>
                        )}
                    </div>
                </FormCard>
            </form>
        </AdminLayout>
    );
}
