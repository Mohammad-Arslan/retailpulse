import PermissionCheckboxes from '@/Components/admin/PermissionCheckboxes';
import AdminFormField from '@/Components/common/AdminFormField';
import FormCard from '@/Components/common/FormCard';
import PageHeader from '@/Components/common/PageHeader';
import { useCan } from '@/Hooks/useCan';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ role, permissionGroups }) {
    const can = useCan();
    const { data, setData, put, processing, errors, delete: destroy } = useForm({
        name: role.name,
        description: role.description ?? '',
        permissions: [...role.permissions],
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.roles.update', role.id));
    };

    const remove = () => {
        if (confirm('Delete this role?')) {
            destroy(route('admin.roles.destroy', role.id));
        }
    };

    return (
        <AdminLayout>
            <Head title="Edit role" />

            <PageHeader title="Edit Role" description={role.name}>
                <Link href={route('admin.roles.index')} className="rp-btn-outline">
                    Back
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="space-y-5">
                <FormCard>
                    <AdminFormField label="Name" id="name" error={errors.name}>
                        <input
                            id="name"
                            value={data.name}
                            className="rp-form-input"
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            disabled={role.is_system}
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
                </FormCard>

                <div className="rp-card max-w-4xl">
                    <h3 className="rp-form-label mb-3">Permissions</h3>
                    <PermissionCheckboxes
                        permissionGroups={permissionGroups}
                        selected={data.permissions}
                        onChange={(permissions) =>
                            setData('permissions', permissions)
                        }
                    />
                </div>

                <div className="flex flex-wrap gap-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rp-btn-primary"
                    >
                        Save changes
                    </button>
                    {can('roles.delete') && !role.is_system && (
                        <button
                            type="button"
                            onClick={remove}
                            className="rp-btn-outline border-rose-200 text-rose-500 hover:border-rose-500 hover:bg-rose-100"
                        >
                            Delete role
                        </button>
                    )}
                </div>
            </form>
        </AdminLayout>
    );
}
